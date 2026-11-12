<?php

namespace App\Services\PrintQueue;

use App\Enums\PrintRequestStatus;
use App\Models\PrintQueueItem;

class SyncLinkedPrintRequest
{
    public function onPlateStarted(PrintQueueItem $item): ?string
    {
        $request = $item->printRequest()->lockForUpdate()->first();
        if (! $request || $request->trashed()) {
            return null;
        }
        if ($request->status === PrintRequestStatus::ACCEPTED) {
            $request->update(['status' => PrintRequestStatus::PRINTING]);

            return 'Request #'.$request->id.' moved to printing.';
        }
        if ($request->status === PrintRequestStatus::PENDING) {
            return 'Request #'.$request->id.' is still pending. Accept it from the request page.';
        }

        return null;
    }

    public function completionNote(PrintQueueItem $item): ?string
    {
        $request = $item->printRequest()->with('user:id,name')->first();
        if (! $request || $request->trashed() || $request->status === PrintRequestStatus::COMPLETE) {
            return null;
        }

        return 'Open request #'.$request->id.' to mark it complete and notify '.($request->user?->name ?? 'the requester').'.';
    }
}
