<?php

namespace App\Services\AdminUsers;

use App\Models\User;

class RestoreUser
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
    ) {}

    public function __invoke(User $actor, User $subject): void
    {
        $subject->restore();

        if (blank($subject->access_revoked_at)) {
            $subject->forceFill([
                'access_revoked_at' => now(),
                'access_revoked_by' => $actor->id,
            ])->save();
        }

        $this->logger->log($actor, $subject, 'user_restored');
    }
}
