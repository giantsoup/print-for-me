<?php

namespace App\Services\PrintQueue;

use App\Enums\PrintQueuePlateStatus;
use App\Models\PrintQueueItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompletePrintQueueItem
{
    public function __construct(private ReorderPrintQueue $ordering, private SyncLinkedPrintRequest $sync) {}

    public function completeIfFinished(PrintQueueItem $item): ?string
    {
        return DB::transaction(function () use ($item): ?string {
            $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($item->id);
            if (! $item->isActive() || ! $item->plates()->exists() || $item->plates()->where('status', '!=', PrintQueuePlateStatus::Done->value)->exists()) {
                return null;
            }

            return $this->complete($item, automatically: true);
        });
    }

    public function complete(PrintQueueItem $item, bool $automatically = false): string
    {
        return DB::transaction(function () use ($item, $automatically): string {
            $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($item->plates()->where('status', PrintQueuePlateStatus::Printing->value)->exists()) {
                throw ValidationException::withMessages(['plate' => 'Finish or requeue the printing plate first.']);
            }
            $item->forceFill(['completed_at' => $item->completed_at ?? now(), 'position' => null])->save();
            $this->ordering->renumber();

            $message = $automatically ? 'All plates done.' : 'Item marked done.';

            return trim($message.' '.($this->sync->onItemCompleted($item) ?? ''));
        });
    }

    public function reopen(PrintQueueItem $item): string
    {
        return DB::transaction(function () use ($item): string {
            $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($item->id);
            if (! $item->isActive()) {
                if ($item->print_request_id !== null && PrintQueueItem::query()->active()
                    ->where('print_request_id', $item->print_request_id)->whereKeyNot($item->id)->exists()) {
                    throw ValidationException::withMessages(['item' => 'Another active item is already linked to this request.']);
                }
                $item->forceFill(['completed_at' => null, 'position' => $this->ordering->append()])->save();
            }
            $this->ordering->renumber();

            return 'Item reopened.';
        });
    }
}
