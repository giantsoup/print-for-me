<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Cache the available columns for the current users table.
     *
     * @var array<string, bool>|null
     */
    protected static ?array $columnCache = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'whitelisted_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function printRequests(): HasMany
    {
        return $this->hasMany(PrintRequest::class);
    }

    public static function hasDatabaseColumn(string $column): bool
    {
        if (self::$columnCache === null) {
            self::$columnCache = array_fill_keys(Schema::getColumnListing((new self)->getTable()), true);
        }

        return self::$columnCache[$column] ?? false;
    }

    public function currentSessionVersion(): int
    {
        if (! self::hasDatabaseColumn('session_version')) {
            return 1;
        }

        return max((int) ($this->session_version ?? 1), 1);
    }

    public function recordLoginContext(?string $ipAddress, ?string $userAgent): void
    {
        $attributes = [
            'last_login_at' => now(),
        ];

        if (self::hasDatabaseColumn('last_login_ip')) {
            $attributes['last_login_ip'] = $ipAddress;
        }

        if (self::hasDatabaseColumn('last_login_user_agent')) {
            $attributes['last_login_user_agent'] = $userAgent;
        }

        $this->forceFill($attributes)->save();
    }
}
