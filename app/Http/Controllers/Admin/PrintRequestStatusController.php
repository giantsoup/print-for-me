<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompletePrintRequestRequest;
use App\Models\PrintRequest;
use App\Notifications\PrintRequestAcceptedNotification;
use App\Notifications\PrintRequestCompletedNotification;
use App\Notifications\PrintRequestRevertedToPendingNotification;
use App\Services\PrintRequests\StoreCompletionPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintRequestStatusController extends Controller
{
    public function __construct(
        public StoreCompletionPhotos $storeCompletionPhotos,
    ) {}

    public function accept(Request $request, PrintRequest $print_request): JsonResponse|RedirectResponse
    {
        if ($print_request->status !== PrintRequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be accepted.',
            ]);
        }

        $print_request->status = PrintRequestStatus::ACCEPTED;
        $print_request->accepted_at = now();
        $print_request->save();

        // Notify requester (queued)
        if ($print_request->user) {
            $print_request->user->notify(new PrintRequestAcceptedNotification($print_request));
        }

        return $this->respond($request, $print_request, 'Request moved to accepted.');
    }

    public function printing(Request $request, PrintRequest $print_request): JsonResponse|RedirectResponse
    {
        if ($print_request->status !== PrintRequestStatus::ACCEPTED) {
            throw ValidationException::withMessages([
                'status' => 'Only accepted requests can be set to printing.',
            ]);
        }

        $print_request->status = PrintRequestStatus::PRINTING;
        $print_request->save();

        return $this->respond($request, $print_request, 'Request moved to printing.');
    }

    public function complete(CompletePrintRequestRequest $request, PrintRequest $print_request): JsonResponse|RedirectResponse
    {
        if ($print_request->status !== PrintRequestStatus::PRINTING) {
            throw ValidationException::withMessages([
                'status' => 'Only printing requests can be completed.',
            ]);
        }

        DB::transaction(function () use ($request, $print_request): void {
            $print_request->status = PrintRequestStatus::COMPLETE;
            $print_request->completed_at = now();
            $print_request->save();

            $this->storeCompletionPhotos->handle($print_request, $request->file('photos', []));
        });

        if ($print_request->user) {
            $print_request->user->notify(new PrintRequestCompletedNotification($print_request));
        }

        return $this->respond($request, $print_request, 'Request marked complete.');
    }

    public function revert(Request $request, PrintRequest $print_request): JsonResponse|RedirectResponse
    {
        if (! in_array($print_request->status, [PrintRequestStatus::ACCEPTED, PrintRequestStatus::PRINTING], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only accepted or printing requests can be reverted to pending.',
            ]);
        }

        $print_request->status = PrintRequestStatus::PENDING;
        $print_request->reverted_at = now();
        $print_request->save();

        // Notify requester (queued)
        if ($print_request->user) {
            $print_request->user->notify(new PrintRequestRevertedToPendingNotification($print_request));
        }

        return $this->respond($request, $print_request, 'Request returned to pending.');
    }

    private function respond(Request $request, PrintRequest $printRequest, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json($printRequest->fresh(['completionPhotos']));
        }

        return back()->with('status', $message);
    }
}
