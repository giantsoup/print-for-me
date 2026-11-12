<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Services\PrintQueue\PrintQueueOverview;
use App\Services\PrintQueue\TransitionPlate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrintQueuePlateStatusController extends Controller
{
    public function __construct(private TransitionPlate $transitions, private PrintQueueOverview $overview) {}

    public function start(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = $this->transitions->start($plate);

        return $this->respond($request, $print_queue_item, $message);
    }

    public function finish(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = $this->transitions->finish($plate);

        return $this->respond($request, $print_queue_item, $message);
    }

    public function fail(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = $this->transitions->fail($plate);

        return $this->respond($request, $print_queue_item, $message);
    }

    public function retry(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = $this->transitions->retry($plate);

        return $this->respond($request, $print_queue_item, $message);
    }

    public function requeue(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = $this->transitions->requeue($plate);

        return $this->respond($request, $print_queue_item, $message);
    }

    public function reopen(Request $request, PrintQueueItem $print_queue_item, PrintQueuePlate $plate): JsonResponse|RedirectResponse
    {
        $message = $this->transitions->reopen($plate);

        return $this->respond($request, $print_queue_item, $message);
    }

    private function respond(Request $request, PrintQueueItem $item, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json($this->overview->presentItem($item->fresh()));
        }

        return back()->with('status', $message);
    }
}
