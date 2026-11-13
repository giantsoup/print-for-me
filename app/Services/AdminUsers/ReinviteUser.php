<?php

namespace App\Services\AdminUsers;

use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use App\Services\Auth\IssueMagicLoginLink;

class ReinviteUser
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
        private readonly IssueMagicLoginLink $issueMagicLoginLink,
    ) {}

    public function __invoke(User $actor, User $subject, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        if (blank($subject->whitelisted_at)) {
            $subject->forceFill([
                'whitelisted_at' => now(),
            ])->save();
        }

        $loginUrl = ($this->issueMagicLoginLink)($subject->email, $ipAddress, $userAgent ?: 'admin-user-reinvite');

        $subject->notify(new MagicLoginLinkNotification($loginUrl));

        $this->logger->log($actor, $subject, 'invite_sent');
    }
}
