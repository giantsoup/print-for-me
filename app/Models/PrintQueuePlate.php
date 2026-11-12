<?php

namespace App\Models;

use App\Enums\PrintQueuePlateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueuePlate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position',
        'status',
        'estimated_minutes',
        'filament',
        'notes',
        'started_at',
        'finished_at',
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
