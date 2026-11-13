<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintQueuePlateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PrintQueue\MovePrintQueuePlateRequest;
use App\Http\Requests\Admin\PrintQueue\StorePrintQueuePlateRequest;
use App\Http\Requests\Admin\PrintQueue\UpdatePrintQueuePlateRequest;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Services\PrintQueue\CompletePrintQueueItem;
use App\Services\PrintQueue\PrintQueueOverview;
use App\Services\PrintQueue\ReorderPrintQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintQueuePlateController extends Controller
{
    public function __construct(private ReorderPrintQueue $ordering, private CompletePrintQueueItem $completion, private PrintQueueOverview $overview) {}

    public function store(StorePrintQueuePlateRequest $request, PrintQueueItem $print_queue_item): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $print_queue_item): void {
            $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($print_queue_item->id);
            if (! $item->isActive()) {
                throw ValidationException::withMessages(['plate' => 'Reopen this item before adding plates.']);
            }
            if ($item->plates()->count() >= PrintQueueItem::MAX_PLATES) {
                throw ValidationException::withMessages(['plate' => 'An item can have up to '.PrintQueueItem::MAX_PLATES.' plates.']);
            }
            $item->plates()->make($request->validated())->forceFill(['position' => (int) $item->plates()->max('position') + 1])->save();
        });

        return $this->respond($request, $print_queue_item, 'Plate added.');
    }

    public function update(UpdatePrintQueuePlateRequest $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $plate->update($request->validated());

        return $this->respond($request, $print_queue_item, 'Plate updated.');
    }

    public function destroy(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = DB::transaction(function () use ($print_queue_item, $plate): string {
            $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($print_queue_item->id);
            $plate = $item->plates()->lockForUpdate()->findOrFail($plate->id);
            if ($plate->status === PrintQueuePlateStatus::Printing) {
                throw ValidationException::withMessages(['plate' => 'Finish or requeue the printing plate first.']);
            }
            if ($item->plates()->count() <= 1) {
                throw ValidationException::withMessages(['plate' => 'An item needs at least one plate. Delete the item instead.']);
            }
            $plate->delete();
            $this->ordering->renumberPlates($item);

            return $this->completion->completeIfFinished($item) ?? 'Plate deleted.';
        });

        return $this->respond($request, $print_queue_item, $message);
    }

    public function move(MovePrintQueuePlateRequest $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $this->ordering->movePlate($plate, $request->validated('direction'));

        return $this->respond($request, $print_queue_item, 'Plate order updated.');
    }

    private function respond(Request $request, PrintQueueItem $item, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json($this->overview->presentItem($item->fresh()));
        }

        return back()->with('status', $message);
    }
}
