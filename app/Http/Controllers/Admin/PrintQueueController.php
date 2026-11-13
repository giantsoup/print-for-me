<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintQueuePlateStatus;
use App\Enums\PrintRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PrintQueue\StorePrintQueueItemRequest;
use App\Http\Requests\Admin\PrintQueue\UpdatePrintQueueItemRequest;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Models\PrintRequest;
use App\Services\PrintQueue\PrintQueueOverview;
use App\Services\PrintQueue\ReorderPrintQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PrintQueueController extends Controller
{
    public function __construct(private PrintQueueOverview $overview, private ReorderPrintQueue $ordering) {}

    public function index(): Response
    {
        return Inertia::render('admin/queue/Index', $this->overview->build());
    }

    public function create(Request $request): Response
    {
        $linked = PrintRequest::query()->where('status', '!=', PrintRequestStatus::COMPLETE)
            ->whereDoesntHave('queueItems', fn (Builder $query): Builder => $query->active())
            ->with($this->overview->userRelation())->find($request->integer('print_request'));
        $linkable = $this->overview->linkableRequests(include: $linked);
        $prefill = $linked ? [
            'print_request_id' => $linked->id,
            'title' => data_get($linked->source_preview, 'title') ?: 'Request #'.$linked->id,
            'for_whom' => $linked->user?->name,
            'needed_by_date' => $linked->needed_by_date?->format('Y-m-d'),
            'notes' => $linked->instructions,
        ] : null;

        return Inertia::render('admin/queue/Create', ['prefill' => $prefill, 'linkableRequests' => $linkable]);
    }

    public function store(StorePrintQueueItemRequest $request): RedirectResponse|JsonResponse
    {
        $item = DB::transaction(function () use ($request): PrintQueueItem {
            $data = $request->validated();
            $attributes = $this->itemAttributes(Arr::except($data, ['plates', 'add_to_top']));
            $item = PrintQueueItem::query()->make($attributes)->forceFill(['position' => $this->ordering->append()]);
            $item->save();
            foreach (array_values($data['plates']) as $index => $plate) {
                $item->plates()->make($plate)->forceFill(['position' => $index + 1])->save();
            }
            if ($request->boolean('add_to_top')) {
                $this->ordering->insertAtTop($item);
            }

            return $item->refresh();
        });
        if ($request->wantsJson()) {
            return response()->json($this->overview->presentItem($item), 201);
        }

        return to_route('admin.print-queue.show', $item)->with('status', 'Added to the queue.');
    }

    public function show(PrintQueueItem $print_queue_item): Response
    {
        $printing = PrintQueuePlate::query()->where('status', PrintQueuePlateStatus::Printing->value)->with('item:id,title')->first();

        return Inertia::render('admin/queue/Show', [
            'item' => $this->overview->presentItem($print_queue_item),
            'printerBusy' => $printing !== null,
            'printingPlate' => $printing ? [
                'id' => $printing->id,
                'item_id' => $printing->print_queue_item_id,
                'item_title' => $printing->item->title,
                'name' => $printing->name,
            ] : null,
            'linkableRequests' => $this->overview->linkableRequests($print_queue_item),
        ]);
    }

    public function update(UpdatePrintQueueItemRequest $request, PrintQueueItem $print_queue_item): RedirectResponse|JsonResponse
    {
        $print_queue_item->update($this->itemAttributes($request->validated()));

        return $this->respond($request, $print_queue_item, 'Queue item updated.');
    }

    public function destroy(Request $request, PrintQueueItem $print_queue_item): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($print_queue_item): void {
            $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($print_queue_item->id);
            if ($item->plates()->where('status', PrintQueuePlateStatus::Printing->value)->exists()) {
                throw ValidationException::withMessages(['item' => 'Finish or requeue the printing plate first.']);
            }
            $item->delete();
            $this->ordering->renumber();
        });
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Queue item deleted.']);
        }

        return to_route('admin.print-queue.index')->with('status', 'Queue item deleted.');
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function itemAttributes(array $data): array
    {
        if (blank($data['for_whom'] ?? null) && filled($data['print_request_id'] ?? null)) {
            $data['for_whom'] = PrintRequest::query()->with($this->overview->userRelation())->find($data['print_request_id'])?->user?->name;
        }

        return $data;
    }

    private function respond(Request $request, PrintQueueItem $item, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json($this->overview->presentItem($item->fresh()));
        }

        return back()->with('status', $message);
    }
}
