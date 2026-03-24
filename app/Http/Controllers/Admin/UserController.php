<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\DeleteUserRequest;
use App\Http\Requests\Admin\Users\DemoteUserRequest;
use App\Http\Requests\Admin\Users\InvalidateUserSessionsRequest;
use App\Http\Requests\Admin\Users\PromoteUserRequest;
use App\Http\Requests\Admin\Users\PurgeUserRequest;
use App\Http\Requests\Admin\Users\ReinviteUserRequest;
use App\Http\Requests\Admin\Users\RestoreUserAccessRequest;
use App\Http\Requests\Admin\Users\RestoreUserRequest;
use App\Http\Requests\Admin\Users\RevokeUserAccessRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\AdminUserEvent;
use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\PrintRequestFile;
use App\Models\User;
use App\Services\AdminUsers\AdminUserEventLogger;
use App\Services\AdminUsers\InvalidateUserSessions;
use App\Services\AdminUsers\PurgeUser;
use App\Services\AdminUsers\ReinviteUser;
use App\Services\AdminUsers\RestoreUser;
use App\Services\AdminUsers\RestoreUserAccess;
use App\Services\AdminUsers\RevokeUserAccess;
use App\Services\AdminUsers\SoftDeleteUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', ''),
            'access' => (string) $request->query('access', ''),
            'lifecycle' => (string) $request->query('lifecycle', ''),
            'request_status' => (string) $request->query('request_status', ''),
        ];

        $query = User::query()
            ->withCount([
                'printRequests as total_requests_count' => fn ($builder) => $builder->withTrashed(),
                'printRequests as pending_requests_count' => fn ($builder) => $builder->where('status', PrintRequestStatus::PENDING),
                'printRequests as accepted_requests_count' => fn ($builder) => $builder->where('status', PrintRequestStatus::ACCEPTED),
                'printRequests as printing_requests_count' => fn ($builder) => $builder->where('status', PrintRequestStatus::PRINTING),
                'printRequests as complete_requests_count' => fn ($builder) => $builder->where('status', PrintRequestStatus::COMPLETE),
                'printRequests as deleted_requests_count' => fn ($builder) => $builder->onlyTrashed(),
            ]);

        match ($filters['lifecycle']) {
            'deleted' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => null,
        };

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($filters['role'] === 'admin') {
            $query->where('is_admin', true);
        }

        if ($filters['role'] === 'member') {
            $query->where('is_admin', false);
        }

        if ($filters['access'] === 'active') {
            $query->whereNotNull('whitelisted_at')->whereNull('access_revoked_at');
        }

        if ($filters['access'] === 'revoked') {
            $query->where(function ($builder): void {
                $builder->whereNull('whitelisted_at')
                    ->orWhereNotNull('access_revoked_at');
            });
        }

        if ($filters['request_status'] === 'none') {
            $query->whereDoesntHave('printRequests', fn ($builder) => $builder->withTrashed());
        }

        if (in_array($filters['request_status'], [...PrintRequestStatus::all(), 'deleted'], true)) {
            $query->whereHas('printRequests', function ($builder) use ($filters): void {
                if ($filters['request_status'] === 'deleted') {
                    $builder->onlyTrashed();

                    return;
                }

                $builder->where('status', $filters['request_status']);
            });
        }

        $users = $query
            ->orderByDesc('last_login_at')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->through(fn (User $user): array => $this->userListItem($user))
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $filters,
            'summaryCounts' => $this->directorySummaryCounts(),
            'availableFilters' => [
                'roles' => ['admin', 'member'],
                'access' => ['active', 'revoked'],
                'lifecycle' => ['active', 'deleted', 'all'],
                'requestStatuses' => [...PrintRequestStatus::all(), 'deleted', 'none'],
            ],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $requestFilters = [
            'status' => (string) $request->query('status', ''),
            'lifecycle' => (string) $request->query('lifecycle', ''),
        ];

        $requestsQuery = PrintRequest::query()
            ->where('user_id', $user->id)
            ->with(['files', 'user:id,name,email'])
            ->withCount('files')
            ->latest();

        match ($requestFilters['lifecycle']) {
            'deleted' => $requestsQuery->onlyTrashed(),
            'all' => $requestsQuery->withTrashed(),
            default => null,
        };

        if (in_array($requestFilters['status'], PrintRequestStatus::all(), true)) {
            $requestsQuery->where('status', $requestFilters['status']);
        }

        $requests = $requestsQuery
            ->paginate(8, ['*'], 'requests_page')
            ->through(fn (PrintRequest $printRequest): array => [
                ...$printRequest->toArray(),
                'availableStatusActions' => $printRequest->availableStatusActions(true),
            ])
            ->withQueryString();

        $auditEvents = AdminUserEvent::query()
            ->with('actor:id,name,email')
            ->where('subject_user_id', $user->id)
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (AdminUserEvent $event): array => $this->auditEventItem($event))
            ->values();

        return Inertia::render('admin/users/Show', [
            'user' => $this->userDetailItem($user),
            'security' => [
                'activeMagicTokens' => MagicLoginToken::query()
                    ->where('email', $user->email)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->count(),
                'sessionVersion' => $user->currentSessionVersion(),
            ],
            'requestCounts' => $this->requestCounts($user),
            'requests' => $requests,
            'requestFilters' => $requestFilters,
            'auditEvents' => $auditEvents,
            'availableActions' => $this->availableActions($request->user(), $user),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        AdminUserEventLogger $logger,
        InvalidateUserSessions $invalidateUserSessions,
    ): RedirectResponse {
        $originalName = $user->name;
        $originalEmail = $user->email;

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            MagicLoginToken::query()
                ->where('email', $originalEmail)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $user->forceFill([
                'email_verified_at' => null,
                'whitelisted_at' => null,
                'access_revoked_at' => null,
                'access_revoked_by' => null,
            ]);
        }

        $user->save();

        if ($originalEmail !== $user->email) {
            $invalidateUserSessions($request->user(), $user, false);
        }

        $logger->log($request->user(), $user, 'user_updated', [
            'changes' => array_filter([
                'name' => $originalName !== $user->name ? ['from' => $originalName, 'to' => $user->name] : null,
                'email' => $originalEmail !== $user->email ? ['from' => $originalEmail, 'to' => $user->email] : null,
            ]),
        ]);

        return back()->with('status', 'User details updated.');
    }

    public function invite(ReinviteUserRequest $request, User $user, ReinviteUser $reinviteUser): RedirectResponse
    {
        $this->ensureUserNotDeleted($user, 'Restore this account before sending a magic link.');
        $this->ensureAccessNotRevoked($user, 'Restore access before sending a magic link.');

        $reinviteUser($request->user(), $user, $request->ip(), $request->userAgent());

        return back()->with('status', "Magic login link sent to {$user->email}.");
    }

    public function revokeAccess(RevokeUserAccessRequest $request, User $user, RevokeUserAccess $revokeUserAccess): RedirectResponse
    {
        $this->ensureUserNotDeleted($user, 'Restore this account before changing access.');

        if (filled($user->access_revoked_at)) {
            return back()->with('status', 'Access is already revoked.');
        }

        $revokeUserAccess($request->user(), $user);

        return back()->with('status', 'User access revoked.');
    }

    public function restoreAccess(
        RestoreUserAccessRequest $request,
        User $user,
        RestoreUserAccess $restoreUserAccess,
    ): RedirectResponse {
        $this->ensureUserNotDeleted($user, 'Restore this account before changing access.');

        if ($user->hasActiveAccess()) {
            return back()->with('status', 'Access is already active.');
        }

        $restoreUserAccess($request->user(), $user);

        return back()->with('status', 'User access restored.');
    }

    public function invalidateSessions(
        InvalidateUserSessionsRequest $request,
        User $user,
        InvalidateUserSessions $invalidateUserSessions,
    ): RedirectResponse {
        $this->ensureUserNotDeleted($user, 'Restore this account before forcing sign-out.');

        $invalidateUserSessions($request->user(), $user);

        return back()->with('status', 'All active sessions will be logged out.');
    }

    public function promote(PromoteUserRequest $request, User $user, AdminUserEventLogger $logger): RedirectResponse
    {
        $this->ensureUserNotDeleted($user, 'Restore this account before changing the admin role.');

        if ($user->is_admin) {
            return back()->with('status', 'This user is already an admin.');
        }

        $user->forceFill(['is_admin' => true])->save();

        $logger->log($request->user(), $user, 'admin_promoted');

        return back()->with('status', 'User promoted to admin.');
    }

    public function demote(DemoteUserRequest $request, User $user, AdminUserEventLogger $logger): RedirectResponse
    {
        $this->ensureUserNotDeleted($user, 'Restore this account before changing the admin role.');
        $this->ensureNotSelf($request->user(), $user, 'You cannot demote your own admin account.');
        $this->ensureNotLastAdmin($user, 'Create or promote another admin before demoting this account.');

        if (! $user->is_admin) {
            return back()->with('status', 'This user is already a member.');
        }

        $user->forceFill(['is_admin' => false])->save();

        $logger->log($request->user(), $user, 'admin_demoted');

        return back()->with('status', 'Admin role removed.');
    }

    public function destroy(DeleteUserRequest $request, User $user, SoftDeleteUser $softDeleteUser): RedirectResponse
    {
        $this->ensureNotSelf($request->user(), $user, 'You cannot delete your own admin account.');
        $this->ensureNotLastAdmin($user, 'Create or promote another admin before deleting this account.');

        if ($user->trashed()) {
            return back()->with('status', 'This account is already deleted.');
        }

        $softDeleteUser($request->user(), $user);

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    public function restore(RestoreUserRequest $request, User $user, RestoreUser $restoreUser): RedirectResponse
    {
        if (! $user->trashed()) {
            return back()->with('status', 'This account is already active.');
        }

        $restoreUser($request->user(), $user);

        return back()->with('status', 'User restored in a revoked state.');
    }

    public function purge(PurgeUserRequest $request, User $user, PurgeUser $purgeUser): RedirectResponse
    {
        $this->ensureNotSelf($request->user(), $user, 'You cannot permanently purge your own admin account.');

        if (! $user->trashed()) {
            $this->validationError('user', 'Delete this account before permanently purging it.');
        }

        $purgeUser($request->user(), $user);

        return redirect()->route('admin.users.index')->with('status', 'User and related data permanently removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function userListItem(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'access_state' => $user->accessState(),
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'request_counts' => [
                'total' => $user->total_requests_count ?? 0,
                'pending' => $user->pending_requests_count ?? 0,
                'accepted' => $user->accepted_requests_count ?? 0,
                'printing' => $user->printing_requests_count ?? 0,
                'complete' => $user->complete_requests_count ?? 0,
                'deleted' => $user->deleted_requests_count ?? 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userDetailItem(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'access_state' => $user->accessState(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'whitelisted_at' => $user->whitelisted_at?->toIso8601String(),
            'access_revoked_at' => $user->access_revoked_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'last_login_user_agent' => $user->last_login_user_agent,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function requestCounts(User $user): array
    {
        $baseQuery = PrintRequest::withTrashed()->where('user_id', $user->id);

        return [
            'all' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', PrintRequestStatus::PENDING)->count(),
            'accepted' => (clone $baseQuery)->where('status', PrintRequestStatus::ACCEPTED)->count(),
            'printing' => (clone $baseQuery)->where('status', PrintRequestStatus::PRINTING)->count(),
            'complete' => (clone $baseQuery)->where('status', PrintRequestStatus::COMPLETE)->count(),
            'deleted' => (clone $baseQuery)->onlyTrashed()->count(),
            'files' => PrintRequestFile::query()
                ->whereIn('print_request_id', PrintRequest::withTrashed()->where('user_id', $user->id)->select('id'))
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function directorySummaryCounts(): array
    {
        $baseQuery = User::withTrashed();

        return [
            'all' => (clone $baseQuery)->count(),
            'admins' => (clone $baseQuery)->where('is_admin', true)->count(),
            'members' => (clone $baseQuery)->where('is_admin', false)->count(),
            'active' => (clone $baseQuery)->whereNotNull('whitelisted_at')->whereNull('access_revoked_at')->whereNull('deleted_at')->count(),
            'revoked' => (clone $baseQuery)->where(function ($builder): void {
                $builder->whereNull('whitelisted_at')
                    ->orWhereNotNull('access_revoked_at');
            })->whereNull('deleted_at')->count(),
            'deleted' => (clone $baseQuery)->onlyTrashed()->count(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function availableActions(User $actor, User $subject): array
    {
        $canInvite = ! $subject->trashed() && blank($subject->access_revoked_at);

        return [
            'canUpdate' => ! $subject->trashed(),
            'canInvite' => $canInvite,
            'canRevokeAccess' => ! $subject->trashed() && $subject->hasActiveAccess(),
            'canRestoreAccess' => ! $subject->trashed() && ! $subject->hasActiveAccess(),
            'canInvalidateSessions' => ! $subject->trashed(),
            'canPromote' => ! $subject->trashed() && ! $subject->is_admin,
            'canDemote' => ! $subject->trashed() && $subject->is_admin && $actor->id !== $subject->id,
            'canDelete' => ! $subject->trashed() && $actor->id !== $subject->id,
            'canRestoreUser' => $subject->trashed(),
            'canPurge' => $subject->trashed() && $actor->id !== $subject->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditEventItem(AdminUserEvent $event): array
    {
        $subject = $event->metadata['subject'] ?? [];
        $changes = $event->metadata['changes'] ?? [];

        return [
            'id' => $event->id,
            'event' => $event->event,
            'title' => match ($event->event) {
                'invite_sent' => 'Magic link sent',
                'access_revoked' => 'Access revoked',
                'access_restored' => 'Access restored',
                'sessions_invalidated' => 'Sessions invalidated',
                'admin_promoted' => 'Promoted to admin',
                'admin_demoted' => 'Demoted to member',
                'user_deleted' => 'Account deleted',
                'user_restored' => 'Account restored',
                'user_purged' => 'Account permanently purged',
                'user_updated' => 'Account details updated',
                default => $event->event,
            },
            'description' => match ($event->event) {
                'user_updated' => $this->updatedDescription($changes),
                'sessions_invalidated' => 'All active sessions for this user were invalidated.',
                'user_purged' => 'The account and all related requests, files, and magic links were permanently removed.',
                default => $subject['email'] ?? 'User record updated.',
            },
            'actor' => $event->actor?->name ?? 'Deleted admin',
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function updatedDescription(array $changes): string
    {
        $parts = [];

        if (isset($changes['name'])) {
            $parts[] = "Name changed to {$changes['name']['to']}.";
        }

        if (isset($changes['email'])) {
            $parts[] = "Email changed to {$changes['email']['to']}.";
        }

        return $parts !== [] ? implode(' ', $parts) : 'Account details were updated.';
    }

    private function ensureUserNotDeleted(User $subject, string $message): void
    {
        if ($subject->trashed()) {
            $this->validationError('user', $message);
        }
    }

    private function ensureAccessNotRevoked(User $subject, string $message): void
    {
        if (filled($subject->access_revoked_at)) {
            $this->validationError('user', $message);
        }
    }

    private function ensureNotSelf(User $actor, User $subject, string $message): void
    {
        if ($actor->id === $subject->id) {
            $this->validationError('user', $message);
        }
    }

    private function ensureNotLastAdmin(User $subject, string $message): void
    {
        if (! $subject->is_admin) {
            return;
        }

        $hasAnotherAdmin = User::query()
            ->where('is_admin', true)
            ->whereNull('deleted_at')
            ->whereKeyNot($subject->id)
            ->exists();

        if (! $hasAnotherAdmin) {
            $this->validationError('user', $message);
        }
    }

    private function validationError(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}
