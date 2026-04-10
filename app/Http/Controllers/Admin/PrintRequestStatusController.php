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
use Illuminate\Support\Facades\Log;
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
            $print_request->loadMissing(['user', 'completionPhotos']);
            $this->logCompletionEmailDispatch($request, $print_request, 'initial_requester_delivery');
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

    public function resendCompletedNotification(Request $request, PrintRequest $print_request): JsonResponse|RedirectResponse
    {
        if ($print_request->status !== PrintRequestStatus::COMPLETE) {
            throw ValidationException::withMessages([
                'status' => 'Only completed requests can resend the completion notice.',
            ]);
        }

        $print_request->loadMissing(['user', 'completionPhotos', 'files']);

        if (blank($print_request->user?->email)) {
            throw ValidationException::withMessages([
                'status' => 'This request does not have a deliverable recipient email.',
            ]);
        }

        $this->logCompletionEmailDispatch($request, $print_request, 'resend_requester_delivery');
        $print_request->user->notify(new PrintRequestCompletedNotification($print_request));

        return $this->respond($request, $print_request, 'Completion email queued again.');
    }

    public function sendCompletedNotificationPreview(Request $request, PrintRequest $print_request): JsonResponse|RedirectResponse
    {
        if ($print_request->status !== PrintRequestStatus::COMPLETE) {
            throw ValidationException::withMessages([
                'status' => 'Only completed requests can send a completion email preview.',
            ]);
        }

        $print_request->loadMissing(['completionPhotos', 'files']);

        if (blank($request->user()?->email)) {
            throw ValidationException::withMessages([
                'status' => 'Your admin account does not have a deliverable email address.',
            ]);
        }

        $this->logCompletionEmailDispatch($request, $print_request, 'admin_preview_delivery');
        $request->user()->notify(new PrintRequestCompletedNotification($print_request));

        return $this->respond($request, $print_request, 'Completion email preview queued to your inbox.');
    }

    private function respond(Request $request, PrintRequest $printRequest, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json($printRequest->fresh(['completionPhotos']));
        }

        return back()->with('status', $message);
    }

    private function logCompletionEmailDispatch(Request $request, PrintRequest $printRequest, string $deliveryMode): void
    {
        Log::info('completion_email.notification_dispatch_requested', [
            'delivery_mode' => $deliveryMode,
            'print_request_id' => $printRequest->getKey(),
            'print_request_status' => (string) $printRequest->status,
            'completion_photo_count' => $printRequest->completionPhotos()->count(),
            'recipient_user_id' => $deliveryMode === 'admin_preview_delivery'
                ? $request->user()?->getKey()
                : $printRequest->user?->getKey(),
            'recipient_email' => $deliveryMode === 'admin_preview_delivery'
                ? $request->user()?->email
                : $printRequest->user?->email,
            'actor_user_id' => $request->user()?->getKey(),
            'actor_email' => $request->user()?->email,
            'queue_connection' => config('queue.default'),
        ]);
    }
}
