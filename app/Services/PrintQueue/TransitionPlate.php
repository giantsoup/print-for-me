<?php

namespace App\Services\PrintQueue;

use App\Enums\PrintQueuePlateStatus;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionPlate
{
    private const BUSY_MESSAGE = 'Another plate is already printing. Finish or requeue it first.';

    public function __construct(private SyncLinkedPrintRequest $sync, private CompletePrintQueueItem $completion) {}

    public function start(PrintQueuePlate $plate): string
    {
        return $this->transition($plate, 'start', PrintQueuePlateStatus::Queued, PrintQueuePlateStatus::Printing);
    }

    public function finish(PrintQueuePlate $plate): string
    {
        return $this->transition($plate, 'finish', PrintQueuePlateStatus::Printing, PrintQueuePlateStatus::Done);
    }

    public function fail(PrintQueuePlate $plate): string
    {
        return $this->transition($plate, 'fail', PrintQueuePlateStatus::Printing, PrintQueuePlateStatus::Failed);
    }

    public function retry(PrintQueuePlate $plate): string
    {
        return $this->transition($plate, 'retry', PrintQueuePlateStatus::Failed, PrintQueuePlateStatus::Queued);
    }

    public function requeue(PrintQueuePlate $plate): string
    {
        return $this->transition($plate, 'requeue', PrintQueuePlateStatus::Printing, PrintQueuePlateStatus::Queued);
    }

    public function reopen(PrintQueuePlate $plate): string
    {
        return $this->transition($plate, 'reopen', PrintQueuePlateStatus::Done, PrintQueuePlateStatus::Queued);
    }

    private function transition(PrintQueuePlate $plate, string $verb, PrintQueuePlateStatus $source, PrintQueuePlateStatus $target): string
    {
        try {
            return DB::transaction(function () use ($plate, $verb, $source, $target): string {
                $item = PrintQueueItem::query()->lockForUpdate()->findOrFail($plate->print_queue_item_id);
                $plate = $item->plates()->lockForUpdate()->findOrFail($plate->id);
                if (! $item->isActive()) {
                    throw ValidationException::withMessages(['status' => 'Reopen this item before changing its plates.']);
                }
                if ($plate->status !== $source || ! $plate->status->canTransitionTo($target)) {
                    throw ValidationException::withMessages(['status' => ucfirst($verb).' is not available for a '.$plate->status->value.' plate.']);
                }
                if ($target === PrintQueuePlateStatus::Printing && PrintQueuePlate::query()
                    ->where('status', PrintQueuePlateStatus::Printing->value)->exists()) {
                    throw ValidationException::withMessages(['status' => self::BUSY_MESSAGE]);
                }
                $plate->forceFill([
                    'status' => $target,
                    'printer_slot' => $target === PrintQueuePlateStatus::Printing ? 1 : null,
                    'started_at' => match ($target) {
                        PrintQueuePlateStatus::Printing => now(),
                        PrintQueuePlateStatus::Queued => null,
                        default => $plate->started_at,
                    },
                    'finished_at' => in_array($target, [PrintQueuePlateStatus::Done, PrintQueuePlateStatus::Failed], true) ? now() : null,
                ])->save();
                $note = $target === PrintQueuePlateStatus::Printing ? $this->sync->onPlateStarted($item) : null;
                if ($target === PrintQueuePlateStatus::Done) {
                    $completed = $this->completion->completeIfFinished($item);
                    if ($completed !== null) {
                        return $completed;
                    }
                }

                $action = match ($verb) {
                    'start' => 'started',
                    'finish' => 'marked done',
                    'fail' => 'marked failed',
                    'retry' => 'queued for retry',
                    'requeue' => 'requeued',
                    'reopen' => 'reopened',
                };

                return trim('Plate '.$plate->position.' '.$action.'. '.($note ?? ''));
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['status' => self::BUSY_MESSAGE]);
        }
    }
}
