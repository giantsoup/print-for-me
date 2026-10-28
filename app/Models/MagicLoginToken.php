<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MagicLoginToken extends Model
{
    use HasFactory;

    public const int EXPIRY_TOLERANCE_SECONDS = 30;

    protected $fillable = [
        'email',
        'token_hash',
        'expires_at',
        'used_at',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function invalidateUnusedForEmail(string $email): void
    {
        $expiredAt = now()->subSeconds(self::EXPIRY_TOLERANCE_SECONDS);

        static::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', $expiredAt)
            ->update(['expires_at' => $expiredAt]);
    }
}
