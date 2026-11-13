<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintQueueItem extends Model
{
    use HasFactory;

    /** The maximum number of plates in a queue item. */
    public const MAX_PLATES = 50;

    /**
     * Lifecycle fields (position, completed_at) are set only through forceFill
     * in the queue services.
     */
    protected $fillable = [
        'print_request_id',
        'title',
        'for_whom',
        'needed_by_date',
        'filament',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'needed_by_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'position' => 'integer',
            'print_request_id' => 'integer',
        ];
    }

    public function plates(): HasMany
    {
        return $this->hasMany(PrintQueuePlate::class)->orderBy('position')->orderBy('id');
    }

    public function printRequest(): BelongsTo
    {
        return $this->belongsTo(PrintRequest::class)->withTrashed();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function isActive(): bool
    {
        return $this->completed_at === null;
    }
}
