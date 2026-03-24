<?php

namespace App\Services\AdminUsers;

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeUserAccess
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
        private readonly InvalidateUserSessions $invalidateUserSessions,
    ) {}

    public function __invoke(User $actor, User $subject): void
    {
        DB::transaction(function () use ($actor, $subject): void {
            MagicLoginToken::query()
                ->where('email', $subject->email)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $subject->forceFill([
                'access_revoked_at' => now(),
                'access_revoked_by' => $actor->id,
            ])->save();

            ($this->invalidateUserSessions)($actor, $subject, false);

            $this->logger->log($actor, $subject, 'access_revoked');
        });
    }
}
