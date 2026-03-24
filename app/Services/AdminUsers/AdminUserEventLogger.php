<?php

namespace App\Services\AdminUsers;

use App\Models\AdminUserEvent;
use App\Models\User;

class AdminUserEventLogger
{
    public function log(User $actor, ?User $subject, string $event, array $metadata = []): AdminUserEvent
    {
        return AdminUserEvent::query()->create([
            'actor_user_id' => $actor->id,
            'subject_user_id' => $subject?->id,
            'event' => $event,
            'metadata' => array_filter([
                'subject' => $subject ? $this->snapshot($subject) : null,
                ...$metadata,
            ], fn (mixed $value): bool => $value !== null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'whitelisted_at' => $user->whitelisted_at?->toIso8601String(),
            'access_revoked_at' => $user->access_revoked_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
        ];
    }
}
