<?php

namespace App\Models;

use App\Enums\PrintQueuePlateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueuePlate extends Model
{
    use HasFactory;

    /**
     * Lifecycle fields (position, status, started_at, finished_at, printer_slot)
     * are set only through forceFill in the queue services.
     */
    protected $fillable = [
        'name',
        'estimated_minutes',
        'filament',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrintQueuePlateStatus::class,
            'position' => 'integer',
            'estimated_minutes' => 'integer',
            'printer_slot' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PrintQueueItem::class, 'print_queue_item_id');
    }
}
