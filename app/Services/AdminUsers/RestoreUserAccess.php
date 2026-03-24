<?php

namespace App\Services\AdminUsers;

use App\Models\User;

class RestoreUserAccess
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
    ) {}

    public function __invoke(User $actor, User $subject): void
    {
        $subject->forceFill([
            'whitelisted_at' => $subject->whitelisted_at ?? now(),
            'access_revoked_at' => null,
            'access_revoked_by' => null,
        ])->save();

        $this->logger->log($actor, $subject, 'access_restored');
    }
}
