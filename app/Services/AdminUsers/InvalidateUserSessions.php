<?php

namespace App\Services\AdminUsers;

use App\Models\User;

class InvalidateUserSessions
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
    ) {}

    public function __invoke(User $actor, User $subject, bool $logEvent = true): void
    {
        if (User::hasDatabaseColumn('session_version')) {
            $subject->forceFill([
                'session_version' => $subject->currentSessionVersion() + 1,
            ])->save();
        }

        if ($logEvent) {
            $this->logger->log($actor, $subject, 'sessions_invalidated', [
                'session_version' => $subject->currentSessionVersion(),
            ]);
        }
    }
}
