<?php

namespace App\Models;

use App\Enums\PrintRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'source_url',
        'source_preview',
        'source_preview_fetched_at',
        'source_preview_failed_at',
        'instructions',
        'accepted_at',
        'reverted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_preview' => 'array',
            'source_preview_fetched_at' => 'datetime',
            'source_preview_failed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'reverted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PrintRequestFile::class);
    }

    public function availableStatusActions(bool $isAdmin): array
    {
        if (! $isAdmin) {
            return [];
        }

        return match ($this->status) {
            PrintRequestStatus::PENDING => ['accept'],
            PrintRequestStatus::ACCEPTED => ['printing', 'revert'],
            PrintRequestStatus::PRINTING => ['complete', 'revert'],
            default => [],
        };
    }
}
