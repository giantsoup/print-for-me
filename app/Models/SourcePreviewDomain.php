<?php

namespace App\Models;

use App\Enums\SourcePreviewFetchPolicy;
use Database\Factories\SourcePreviewDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcePreviewDomain extends Model
{
    /** @use HasFactory<SourcePreviewDomainFactory> */
    use HasFactory;

    protected $fillable = [
        'domain',
        'label',
        'policy',
        'last_seen_print_request_id',
        'last_seen_url',
        'last_seen_at',
        'last_attempted_at',
        'last_attempt_status',
        'last_success_at',
        'last_failure_at',
    ];

    protected function casts(): array
    {
        return [
            'policy' => SourcePreviewFetchPolicy::class,
            'last_seen_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function lastSeenPrintRequest(): BelongsTo
    {
        return $this->belongsTo(PrintRequest::class, 'last_seen_print_request_id');
    }
}
