<?php

namespace App\Services\PrintQueue;

use App\Enums\PrintRequestStatus;
use App\Models\PrintQueueItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Moves an accepted request to printing without notifying anyone and returns
     * the flash note for the completed item.
     */
    public function onItemCompleted(PrintQueueItem $item): ?string
    {
        $request = $item->printRequest()->lockForUpdate()->with([
            'user' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->select(['id', 'name']),
        ])->first();
        if (! $request || $request->trashed() || $request->status === PrintRequestStatus::COMPLETE) {
            return null;
        }

        if ($request->status === PrintRequestStatus::ACCEPTED) {
            $request->update(['status' => PrintRequestStatus::PRINTING]);
        }
        if ($request->status === PrintRequestStatus::PENDING) {
            return 'Request #'.$request->id.' is still pending. Accept it, move it to printing, then mark it complete to notify '.($request->user?->name ?? 'the requester').'.';
        }

        return 'Open request #'.$request->id.' to mark it complete and notify '.($request->user?->name ?? 'the requester').'.';
    }
}
