<?php

namespace App\Services\PrintQueue;

use App\Enums\PrintQueuePlateStatus;
use App\Enums\PrintRequestStatus;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Models\PrintRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintQueueOverview
{
    /**
     * @return array{
     *     nowPrinting: ?array,
     *     upNext: ?array,
     *     items: array,
     *     doneItems: array,
     *     doneCount: int,
     *     totals: array{active_items: int, remaining_plates: int, remaining_minutes: int, unestimated_plates: int}
     * }
     */
    public function build(): array
    {
        $items = PrintQueueItem::query()->active()->with(['plates', ...$this->requesterRelation()])->orderBy('position')->orderBy('id')->get();
        $printerBusy = PrintQueuePlate::query()->where('status', PrintQueuePlateStatus::Printing->value)->exists();
        $nowPrinting = null;
        $upNext = null;
        $presented = [];
        foreach ($items as $item) {
            foreach ($item->plates as $plate) {
                $plate->setRelation('item', $item);
                $slot = $this->presentSlot($plate, $printerBusy);
                if ($plate->status === PrintQueuePlateStatus::Printing) {
                    $nowPrinting = $slot;
                }
                if ($plate->status === PrintQueuePlateStatus::Queued && $upNext === null) {
                    $upNext = $slot;
                }
            }
            $presented[] = $this->presentItem($item, printerBusy: $printerBusy);
        }
        $done = PrintQueueItem::query()->completed()->with($this->requesterRelation())->withCount('plates');
        foreach (PrintQueuePlateStatus::cases() as $status) {
            $done->withCount(['plates as '.$status->value.'_count' => fn (Builder $query): Builder => $query->where('status', $status->value)]);
        }
        $done->withSum(['plates as remaining_minutes' => fn (Builder $query): Builder => $query->where('status', '!=', 'done')], 'estimated_minutes')
            ->withCount([
                'plates as unestimated_plates' => fn (Builder $query): Builder => $query
                    ->where('status', '!=', 'done')->whereNull('estimated_minutes'),
            ]);
        $doneItems = $done->orderByDesc('completed_at')->orderByDesc('id')->limit(10)->get()
            ->map(fn (PrintQueueItem $item): array => $this->presentItem($item, false, $printerBusy))->all();

        return [
            'nowPrinting' => $nowPrinting,
            'upNext' => $upNext,
            'items' => $presented,
            'doneItems' => $doneItems,
            'doneCount' => PrintQueueItem::query()->completed()->count(),
            'totals' => [
                'active_items' => count($presented),
                'remaining_plates' => array_sum(array_map(fn (array $item): int => $item['plate_counts']['total'] - $item['plate_counts']['done'], $presented)),
                'remaining_minutes' => array_sum(array_column($presented, 'remaining_minutes')),
                'unestimated_plates' => array_sum(array_column($presented, 'unestimated_plates')),
            ],
        ];
    }

    /**
     * @return array{
     *     nowPrinting: ?array,
     *     upNext: ?array,
     *     awaitingCompletion: array{count: int, requests: list<array{id: int, user_name: ?string, item_id: int, item_title: string}>},
     *     totals: array{active_items: int, remaining_plates: int, remaining_minutes: int, unestimated_plates: int}
     * }
     */
    public function dashboard(): array
    {
        $plates = PrintQueuePlate::query()->whereHas('item', fn (Builder $query): Builder => $query->active());
        $printing = (clone $plates)->where('status', PrintQueuePlateStatus::Printing->value)->with('item')->first();
        $printerBusy = $printing !== null;
        $next = (clone $plates)->where('status', PrintQueuePlateStatus::Queued->value)
            ->orderBy(PrintQueueItem::query()->select('position')->whereColumn('print_queue_items.id', 'print_queue_plates.print_queue_item_id'))
            ->orderBy('print_queue_item_id')->orderBy('position')->orderBy('id')
            ->with('item')->first();
        $remaining = (clone $plates)->where('status', '!=', PrintQueuePlateStatus::Done->value)
            ->selectRaw('COUNT(*) as remaining_plates, SUM(estimated_minutes) as remaining_minutes, COUNT(*) - COUNT(estimated_minutes) as unestimated_plates')
            ->first();

        $awaitingCompletion = PrintRequest::query()->where('status', '!=', PrintRequestStatus::COMPLETE)
            ->whereHas('queueItems', fn (Builder $query): Builder => $query->completed())
            ->whereDoesntHave('queueItems', fn (Builder $query): Builder => $query->active());
        $awaitingCount = (clone $awaitingCompletion)->count();
        $awaitingRequests = $awaitingCompletion->with([
            ...$this->userRelation(),
            'queueItems' => fn (HasMany $query): HasMany => $query
                ->select(['id', 'print_request_id', 'title'])->reorder()->latest('completed_at')->latest('id')->limit(1),
        ])->latest('updated_at')->latest('id')->limit(3)->get()
            ->map(function (PrintRequest $request): array {
                $item = $request->queueItems->first();

                return [
                    'id' => $request->id,
                    'user_name' => $request->user?->name,
                    'item_id' => $item->id,
                    'item_title' => $item->title,
                ];
            })->all();

        return [
            'awaitingCompletion' => ['count' => $awaitingCount, 'requests' => $awaitingRequests],
            'nowPrinting' => $printing !== null ? $this->presentSlot($printing, $printerBusy) : null,
            'upNext' => $next !== null ? $this->presentSlot($next, $printerBusy) : null,
            'totals' => [
                'active_items' => PrintQueueItem::query()->active()->count(),
                'remaining_plates' => (int) $remaining->remaining_plates,
                'remaining_minutes' => (int) $remaining->remaining_minutes,
                'unestimated_plates' => (int) $remaining->unestimated_plates,
            ],
        ];
    }

    /** @return array{item: array, plate: array} */
    private function presentSlot(PrintQueuePlate $plate, bool $printerBusy): array
    {
        return [
            'item' => $plate->item->only(['id', 'title', 'for_whom']) + [
                'needed_by_date' => $plate->item->needed_by_date?->format('Y-m-d'),
            ],
            'plate' => $this->presentPlate($plate, $printerBusy),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     position: ?int,
     *     for_whom: ?string,
     *     needed_by_date: ?string,
     *     filament: ?string,
     *     notes: ?string,
     *     completed_at: ?string,
     *     plate_counts: array,
     *     remaining_minutes: int,
     *     unestimated_plates: int,
     *     linked_request: ?array,
     *     plates?: array
     * }
     */
    public function presentItem(PrintQueueItem $item, bool $withPlates = true, ?bool $printerBusy = null): array
    {
        $printerBusy ??= PrintQueuePlate::query()->where('status', PrintQueuePlateStatus::Printing->value)->exists();
        $item->loadMissing($this->requesterRelation());
        if ($withPlates) {
            $item->loadMissing('plates');
        }
        $counts = ['total' => $withPlates ? $item->plates->count() : (int) $item->plates_count];
        foreach (PrintQueuePlateStatus::cases() as $status) {
            $counts[$status->value] = $withPlates ? $item->plates->where('status', $status)->count() : (int) $item->getAttribute($status->value.'_count');
        }
        $remaining = $withPlates ? $item->plates->reject(fn (PrintQueuePlate $plate): bool => $plate->status === PrintQueuePlateStatus::Done) : null;
        $request = $item->printRequest;
        $result = [
            'id' => $item->id,
            'title' => $item->title,
            'position' => $item->position,
            'for_whom' => $item->for_whom,
            'needed_by_date' => $item->needed_by_date?->format('Y-m-d'),
            'filament' => $item->filament,
            'notes' => $item->notes,
            'completed_at' => $item->completed_at?->toIso8601String(),
            'plate_counts' => $counts,
            'remaining_minutes' => (int) ($withPlates ? $remaining->sum('estimated_minutes') : $item->remaining_minutes),
            'unestimated_plates' => $withPlates ? $remaining->whereNull('estimated_minutes')->count() : (int) $item->unestimated_plates,
            'linked_request' => $request ? [
                'id' => $request->id,
                'status' => $request->status,
                'user_name' => $request->user?->name,
                'is_deleted' => $request->trashed(),
            ] : null,
        ];
        if ($withPlates) {
            $result['plates'] = $item->plates->map(function (PrintQueuePlate $plate) use ($item, $printerBusy): array {
                $plate->setRelation('item', $item);

                return $this->presentPlate($plate, $printerBusy);
            })->all();
        }

        return $result;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     position: int,
     *     status: string,
     *     estimated_minutes: ?int,
     *     filament: ?string,
     *     notes: ?string,
     *     started_at: ?string,
     *     finished_at: ?string,
     *     availableActions: list<string>
     * }
     */
    private function presentPlate(PrintQueuePlate $plate, bool $printerBusy): array
    {
        $actions = [];
        if ($plate->item->isActive()) {
            $targets = [
                'start' => PrintQueuePlateStatus::Printing,
                'finish' => PrintQueuePlateStatus::Done,
                'fail' => PrintQueuePlateStatus::Failed,
                'retry' => PrintQueuePlateStatus::Queued,
                'requeue' => PrintQueuePlateStatus::Queued,
                'reopen' => PrintQueuePlateStatus::Queued,
            ];
            foreach ($targets as $action => $target) {
                $sourceMatches = match ($action) {
                    'retry' => $plate->status === PrintQueuePlateStatus::Failed,
                    'requeue' => $plate->status === PrintQueuePlateStatus::Printing,
                    'reopen' => $plate->status === PrintQueuePlateStatus::Done,
                    default => true,
                };
                if ($sourceMatches && $plate->status->canTransitionTo($target) && ($action !== 'start' || ! $printerBusy)) {
                    $actions[] = $action;
                }
            }
        }

        return [
            'id' => $plate->id,
            'name' => $plate->name,
            'position' => $plate->position,
            'status' => $plate->status->value,
            'estimated_minutes' => $plate->estimated_minutes,
            'filament' => $plate->filament,
            'notes' => $plate->notes,
            'started_at' => $plate->started_at?->toIso8601String(),
            'finished_at' => $plate->finished_at?->toIso8601String(),
            'availableActions' => $actions,
        ];
    }

    /** @return array<string, \Closure> */
    public function requesterRelation(): array
    {
        return ['printRequest.user' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->select(['id', 'name'])];
    }

    /** @return array<string, \Closure> */
    public function userRelation(): array
    {
        return ['user' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->select(['id', 'name'])];
    }

    /** @return list<array{id: int, label: string, user_name: ?string}> */
    public function linkableRequests(?PrintQueueItem $except = null, ?PrintRequest $include = null): array
    {
        $requests = PrintRequest::query()->where('status', '!=', PrintRequestStatus::COMPLETE)
            ->whereDoesntHave('queueItems', function (Builder $query) use ($except): void {
                $query->active();
                if ($except !== null) {
                    $query->whereKeyNot($except->id);
                }
            })->with($this->userRelation())->latest()->orderByDesc('id')->limit(100)->get();
        if ($except?->print_request_id && ! $requests->contains('id', $except->print_request_id)) {
            $current = $except->printRequest()->with($this->userRelation())->first();
            if ($current !== null) {
                $requests->prepend($current);
            }
        }
        if ($include !== null && ! $requests->contains('id', $include->id)) {
            $requests->push($include);
        }

        return $requests->map(fn (PrintRequest $request): array => [
            'id' => $request->id,
            'label' => implode(' · ', array_filter(['Request #'.$request->id, $request->user?->name, ucfirst($request->status)])),
            'user_name' => $request->user?->name,
        ])->all();
    }
}
