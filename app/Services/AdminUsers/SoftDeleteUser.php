<?php

namespace App\Services\AdminUsers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SoftDeleteUser
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
        private readonly RevokeUserAccess $revokeUserAccess,
    ) {}

    public function __invoke(User $actor, User $subject): void
    {
        DB::transaction(function () use ($actor, $subject): void {
            if (blank($subject->access_revoked_at)) {
                ($this->revokeUserAccess)($actor, $subject);
            }

            $subject->delete();

            $this->logger->log($actor, $subject, 'user_deleted');
        });
    }
}
