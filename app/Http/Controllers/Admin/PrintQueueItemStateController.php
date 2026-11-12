<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PrintQueue\MovePrintQueueItemRequest;
use App\Models\PrintQueueItem;
use App\Services\PrintQueue\CompletePrintQueueItem;
use App\Services\PrintQueue\PrintQueueOverview;
use App\Services\PrintQueue\ReorderPrintQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrintQueueItemStateController extends Controller
{
    public function __construct(private ReorderPrintQueue $ordering, private CompletePrintQueueItem $completion, private PrintQueueOverview $overview) {}

    public function move(MovePrintQueueItemRequest $request, PrintQueueItem $print_queue_item): JsonResponse|RedirectResponse
    {
        $this->ordering->move($print_queue_item, $request->validated('direction'));

        return $this->respond($request, $print_queue_item, 'Queue order updated.');
    }

    public function complete(Request $request, PrintQueueItem $print_queue_item): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $print_queue_item, $this->completion->complete($print_queue_item));
    }

    public function reopen(Request $request, PrintQueueItem $print_queue_item): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $print_queue_item, $this->completion->reopen($print_queue_item));
    }

    private function respond(Request $request, PrintQueueItem $item, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json($this->overview->presentItem($item->fresh()));
        }

        return back()->with('status', $message);
    }
}
