<?php

namespace App\Policies;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;

class PrintRequestPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, PrintRequest $printRequest): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin ?? false) {
            return true;
        }

        return $user->id === $printRequest->user_id;
    }

    /**
     * Determine whether the user can update the model.
     * Owner if status = pending and not deleted; admin always.
     */
    public function update(?User $user, PrintRequest $printRequest): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin ?? false) {
            return true;
        }

        return $user->id === $printRequest->user_id
            && $printRequest->status === PrintRequestStatus::PENDING
            && ! $printRequest->trashed();
    }

    /**
     * Soft delete: owner if pending.
     */
    public function delete(?User $user, PrintRequest $printRequest): bool
    {
        if (! $user) {
            return false;
        }

        // Only the owner can soft delete when pending (admin not included per guide)
        return $user->id === $printRequest->user_id
            && $printRequest->status === PrintRequestStatus::PENDING
            && ! $printRequest->trashed();
    }

    /**
     * Force delete: owner on their soft-deleted pending request or admin.
     */
    public function forceDelete(?User $user, PrintRequest $printRequest): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin ?? false) {
            return true;
        }

        return $user->id === $printRequest->user_id
            && $printRequest->trashed()
            && $printRequest->status === PrintRequestStatus::PENDING;
    }

    /**
     * Download: owner or admin.
     */
    public function download(?User $user, PrintRequest $printRequest): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin ?? false) {
            return true;
        }

        return $user->id === $printRequest->user_id;
    }
}
