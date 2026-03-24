<?php

namespace App\Services\AdminUsers;

use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Support\Facades\URL;

class ReinviteUser
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
    ) {}

    public function __invoke(User $actor, User $subject, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        if (blank($subject->whitelisted_at)) {
            $subject->forceFill([
                'whitelisted_at' => now(),
            ])->save();
        }

        MagicLoginToken::query()
            ->where('email', $subject->email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        MagicLoginToken::query()->create([
            'email' => $subject->email,
            'token_hash' => $hash,
            'expires_at' => now()->addMinutes(10),
            'ip' => $ipAddress,
            'user_agent' => $userAgent ?: 'admin-user-reinvite',
        ]);

        $loginUrl = URL::temporarySignedRoute(
            'magic.login',
            now()->addMinutes(10),
            ['email' => $subject->email, 'token' => $raw]
        );

        $subject->notify(new MagicLoginLinkNotification($loginUrl));

        $this->logger->log($actor, $subject, 'invite_sent');
    }
}
