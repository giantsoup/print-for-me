<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintRequestCompletionPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'print_request_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sort_order',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function printRequest(): BelongsTo
    {
        return $this->belongsTo(PrintRequest::class);
    }
}
