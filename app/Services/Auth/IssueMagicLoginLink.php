<?php

namespace App\Services\Auth;

use App\Models\MagicLoginToken;
use Illuminate\Support\Facades\URL;

class IssueMagicLoginLink
{
    public const int EXPIRY_MINUTES = 10;

    /**
     * Supersede unused links and return a signed URL with a hashed, single-use token.
     * Callers must first verify that the account may sign in.
     */
    public function __invoke(string $email, ?string $ipAddress, ?string $userAgent): string
    {
        MagicLoginToken::invalidateUnusedForEmail($email);

        $rawToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES);

        MagicLoginToken::query()->create([
            'email' => $email,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => $expiresAt,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return URL::temporarySignedRoute('magic.login', $expiresAt, ['email' => $email, 'token' => $rawToken]);
    }
}
