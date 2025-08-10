<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\PrintRequest;
use App\Notifications\PrintRequestAcceptedNotification;
use App\Notifications\PrintRequestCompletedNotification;
use App\Notifications\PrintRequestRevertedToPendingNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrintRequestStatusController extends Controller
{
    public function accept(Request $request, PrintRequest $print_request)
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

        return response()->json($print_request);
    }

    public function printing(Request $request, PrintRequest $print_request)
    {
        if ($print_request->status !== PrintRequestStatus::ACCEPTED) {
            throw ValidationException::withMessages([
                'status' => 'Only accepted requests can be set to printing.',
            ]);
        }

        $print_request->status = PrintRequestStatus::PRINTING;
        $print_request->save();

        return response()->json($print_request);
    }

    public function complete(Request $request, PrintRequest $print_request)
    {
        if ($print_request->status !== PrintRequestStatus::PRINTING) {
            throw ValidationException::withMessages([
                'status' => 'Only printing requests can be completed.',
            ]);
        }

        $print_request->status = PrintRequestStatus::COMPLETE;
        $print_request->completed_at = now();
        $print_request->save();

        // Notify requester (queued)
        if ($print_request->user) {
            $print_request->user->notify(new PrintRequestCompletedNotification($print_request));
        }

        return response()->json($print_request);
    }

    public function revert(Request $request, PrintRequest $print_request)
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

        return response()->json($print_request);
    }
}
