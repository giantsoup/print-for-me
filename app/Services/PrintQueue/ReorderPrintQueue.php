<?php

namespace App\Services\PrintQueue;

use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReorderPrintQueue
{
    public function append(): int
    {
        return DB::transaction(fn (): int => (int) PrintQueueItem::query()->active()->max('position') + 1);
    }

    public function insertAtTop(PrintQueueItem $item): void
    {
        $this->move($item, 'top');
    }

    public function move(PrintQueueItem $item, string $direction): void
    {
        DB::transaction(function () use ($item, $direction): void {
            $items = PrintQueueItem::query()->active()->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $this->rewrite($items, $item->id, $direction);
        });
    }

    public function renumber(): void
    {
        DB::transaction(function (): void {
            $this->rewrite(PrintQueueItem::query()->active()->orderBy('position')->orderBy('id')->lockForUpdate()->get());
        });
    }

    public function movePlate(PrintQueuePlate $plate, string $direction): void
    {
        DB::transaction(function () use ($plate, $direction): void {
            $this->rewrite($plate->item->plates()->lockForUpdate()->get(), $plate->id, $direction);
        });
    }

    public function renumberPlates(PrintQueueItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $this->rewrite($item->plates()->lockForUpdate()->get());
        });
    }

    /** @param Collection<int, PrintQueueItem>|Collection<int, PrintQueuePlate> $models */
    private function rewrite(Collection $models, ?int $movingId = null, ?string $direction = null): void
    {
        $ordered = $models->all();
        $index = array_search($movingId, $models->modelKeys(), true);
        if ($movingId !== null && $index !== false) {
            $target = match ($direction) {
                'top' => 0,
                'up' => max(0, $index - 1),
                'down' => min(count($ordered) - 1, $index + 1),
                default => $index,
            };
            $moving = array_splice($ordered, $index, 1);
            array_splice($ordered, $target, 0, $moving);
        }
        foreach ($ordered as $position => $model) {
            $model->forceFill(['position' => $position + 1])->save();
        }
    }
}
