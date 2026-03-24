<?php

use App\Enums\PrintRequestStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\AdminUserEvent;
use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function adminUser(array $attributes = []): User
{
    return User::factory()->create([
        'is_admin' => true,
        'whitelisted_at' => now(),
        'last_login_at' => now(),
        ...$attributes,
    ]);
}

function issueMagicLink(User $user, ?string $email = null): string
{
    $targetEmail = $email ?? $user->email;
    $raw = Str::random(64);

    MagicLoginToken::create([
        'email' => $targetEmail,
        'token_hash' => hash('sha256', $raw),
        'expires_at' => now()->addMinutes(10),
    ]);

    return URL::temporarySignedRoute('magic.login', now()->addMinutes(10), [
        'email' => $targetEmail,
        'token' => $raw,
    ]);
}

it('blocks non-admins from admin user management routes', function () {
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $subject = User::factory()->create(['whitelisted_at' => now()]);

    actingAs($user);

    get(route('admin.users.index'))->assertForbidden();
    get(route('admin.users.show', $subject))->assertForbidden();
    patch(route('admin.users.update', $subject), ['name' => 'Changed', 'email' => 'changed@example.com'])->assertForbidden();
    delete(route('admin.users.destroy', $subject))->assertForbidden();
});

it('filters the admin user directory by query, access, lifecycle, and request activity', function () {
    $admin = adminUser();
    $match = User::factory()->create([
        'name' => 'Revoked Member',
        'email' => 'revoked@example.com',
        'whitelisted_at' => now(),
        'access_revoked_at' => now(),
    ]);
    $deleted = User::factory()->create([
        'name' => 'Deleted Member',
        'email' => 'deleted@example.com',
        'whitelisted_at' => now(),
    ]);
    $deleted->delete();
    $other = adminUser([
        'name' => 'Other Admin',
        'email' => 'other-admin@example.com',
    ]);

    PrintRequest::create([
        'user_id' => $match->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/revoked',
    ]);

    actingAs($admin);

    get(route('admin.users.index', [
        'q' => 'revoked',
        'role' => 'member',
        'access' => 'revoked',
        'lifecycle' => 'all',
        'request_status' => 'pending',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('users.data.0.email', 'revoked@example.com')
            ->where('users.data', fn ($items) => count($items) === 1)
            ->where('summaryCounts.deleted', 1)
        );

    get(route('admin.users.index', ['lifecycle' => 'deleted']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('users.data.0.email', 'deleted@example.com')
            ->where('users.data', fn ($items) => count($items) === 1)
        );

    expect($other->email)->toBe('other-admin@example.com');
});

it('loads the user detail workspace with account, request, security, and audit data', function () {
    Storage::fake('local');

    $admin = adminUser();
    $subject = User::factory()->create([
        'name' => 'Maker Person',
        'email' => 'maker@example.com',
        'whitelisted_at' => now(),
        'last_login_at' => now(),
        'last_login_ip' => '127.0.0.1',
        'last_login_user_agent' => 'Safari',
    ]);

    $activeRequest = PrintRequest::create([
        'user_id' => $subject->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/one',
    ]);
    $deletedRequest = PrintRequest::create([
        'user_id' => $subject->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/two',
    ]);
    $deletedRequest->delete();

    $activeRequest->files()->create([
        'disk' => 'local',
        'path' => 'prints/test/file.stl',
        'original_name' => 'file.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 4096,
        'sha256' => hash('sha256', 'file'),
    ]);

    MagicLoginToken::create([
        'email' => $subject->email,
        'token_hash' => hash('sha256', 'active-token'),
        'expires_at' => now()->addMinutes(10),
    ]);

    AdminUserEvent::create([
        'actor_user_id' => $admin->id,
        'subject_user_id' => $subject->id,
        'event' => 'invite_sent',
        'metadata' => [
            'subject' => [
                'email' => $subject->email,
            ],
        ],
    ]);

    actingAs($admin);

    get(route('admin.users.show', ['user' => $subject->id, 'lifecycle' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('user.email', 'maker@example.com')
            ->where('security.activeMagicTokens', 1)
            ->where('requestCounts.deleted', 1)
            ->where('requestCounts.files', 1)
            ->where('requests.data', fn ($items) => collect($items)->pluck('id')->contains($activeRequest->id))
            ->where('auditEvents.0.title', 'Magic link sent')
        );
});

it('reinvites a user and records an audit event', function () {
    Notification::fake();

    $admin = adminUser();
    $subject = User::factory()->create([
        'email' => 'friend@example.com',
        'whitelisted_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.users.invite', $subject))
        ->assertRedirect()
        ->assertSessionHas('status', 'Magic login link sent to friend@example.com.');

    Notification::assertSentTo($subject, MagicLoginLinkNotification::class);
    assertDatabaseHas('admin_user_events', [
        'event' => 'invite_sent',
        'subject_user_id' => $subject->id,
    ]);
});

it('revokes access, expires tokens, invalidates sessions, and blocks future magic logins', function () {
    $admin = adminUser();
    $subject = User::factory()->create([
        'email' => 'revoked-login@example.com',
        'whitelisted_at' => now(),
        'session_version' => 1,
    ]);

    MagicLoginToken::create([
        'email' => $subject->email,
        'token_hash' => hash('sha256', 'revoke-me'),
        'expires_at' => now()->addMinutes(10),
    ]);

    actingAs($admin);

    post(route('admin.users.access.revoke', $subject))
        ->assertRedirect()
        ->assertSessionHas('status', 'User access revoked.');

    $subject->refresh();

    expect($subject->access_revoked_at)->not->toBeNull()
        ->and($subject->session_version)->toBe(2);

    expect(MagicLoginToken::where('email', $subject->email)->first()?->expires_at?->lte(now()))->toBeTrue();

    assertDatabaseHas('admin_user_events', [
        'event' => 'access_revoked',
        'subject_user_id' => $subject->id,
    ]);

    auth()->guard('web')->logout();

    post(route('magic.send'), ['email' => $subject->email])->assertSessionHasErrors('email');

    $url = issueMagicLink($subject);
    get($url)->assertRedirect(route('magic.result'))->assertSessionHasErrors('email');
});

it('restores access and allows requesting a new magic link again', function () {
    Notification::fake();

    $admin = adminUser();
    $subject = User::factory()->create([
        'email' => 'restore@example.com',
        'whitelisted_at' => now(),
        'access_revoked_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.users.access.restore', $subject))
        ->assertRedirect()
        ->assertSessionHas('status', 'User access restored.');

    $subject->refresh();

    expect($subject->access_revoked_at)->toBeNull();

    assertDatabaseHas('admin_user_events', [
        'event' => 'access_restored',
        'subject_user_id' => $subject->id,
    ]);

    auth()->guard('web')->logout();

    post(route('magic.send'), ['email' => $subject->email])->assertSessionHasNoErrors();
    Notification::assertSentTo($subject, MagicLoginLinkNotification::class);
});

it('invalidates sessions and requires fresh access after an email change', function () {
    $admin = adminUser();
    $subject = User::factory()->create([
        'name' => 'Before',
        'email' => 'before@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
        'session_version' => 4,
    ]);

    MagicLoginToken::create([
        'email' => 'before@example.com',
        'token_hash' => hash('sha256', 'email-change'),
        'expires_at' => now()->addMinutes(10),
    ]);

    actingAs($admin);

    patch(route('admin.users.update', $subject), [
        'name' => 'After',
        'email' => 'after@example.com',
    ])->assertRedirect()->assertSessionHas('status', 'User details updated.');

    $subject->refresh();

    expect($subject->name)->toBe('After')
        ->and($subject->email)->toBe('after@example.com')
        ->and($subject->email_verified_at)->toBeNull()
        ->and($subject->whitelisted_at)->toBeNull()
        ->and($subject->session_version)->toBe(5);

    expect(MagicLoginToken::where('email', 'before@example.com')->first()?->expires_at?->lte(now()))->toBeTrue();

    auth()->guard('web')->logout();

    post(route('magic.send'), ['email' => 'after@example.com'])->assertSessionHasErrors('email');

    $event = AdminUserEvent::where('event', 'user_updated')->first();
    expect($event?->metadata['changes']['email']['to'] ?? null)->toBe('after@example.com');
});

it('promotes and demotes admins with self and last-admin guardrails', function () {
    $admin = adminUser(['email' => 'primary-admin@example.com']);
    $secondary = User::factory()->create([
        'email' => 'member@example.com',
        'whitelisted_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.users.role.promote', $secondary))
        ->assertRedirect()
        ->assertSessionHas('status', 'User promoted to admin.');

    $secondary->refresh();
    expect($secondary->is_admin)->toBeTrue();

    post(route('admin.users.role.demote', $secondary))
        ->assertRedirect()
        ->assertSessionHas('status', 'Admin role removed.');

    $secondary->refresh();
    expect($secondary->is_admin)->toBeFalse();

    post(route('admin.users.role.demote', $admin))
        ->assertSessionHasErrors('user');

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('blocks demoting the last remaining admin', function () {
    $admin = adminUser(['email' => 'solo-admin@example.com']);
    $member = User::factory()->create(['whitelisted_at' => now()]);

    actingAs($admin);

    post(route('admin.users.role.demote', $admin))
        ->assertSessionHasErrors('user');

    expect((bool) $member->fresh()->is_admin)->toBeFalse();
});

it('soft deletes and restores a user while keeping the restored account revoked', function () {
    $admin = adminUser();
    $subject = User::factory()->create([
        'email' => 'soft-delete@example.com',
        'whitelisted_at' => now(),
        'session_version' => 2,
    ]);

    actingAs($admin);

    delete(route('admin.users.destroy', $subject))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', 'User deleted.');

    $subject->refresh();

    expect($subject->trashed())->toBeTrue()
        ->and($subject->access_revoked_at)->not->toBeNull()
        ->and($subject->session_version)->toBe(3);

    get(route('admin.users.index', ['q' => $subject->email]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('users.data', fn ($items) => count($items) === 0)
        );

    get(route('admin.users.index', ['q' => $subject->email, 'lifecycle' => 'deleted']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('users.data.0.email', $subject->email)
        );

    post(route('admin.users.restore', ['user' => $subject->id]))
        ->assertRedirect()
        ->assertSessionHas('status', 'User restored in a revoked state.');

    $subject->refresh();

    expect($subject->trashed())->toBeFalse()
        ->and($subject->access_revoked_at)->not->toBeNull();
});

it('permanently purges a deleted user and their related data while preserving the audit snapshot', function () {
    Storage::fake('local');

    $admin = adminUser();
    $subject = User::factory()->create([
        'email' => 'purge@example.com',
        'whitelisted_at' => now(),
    ]);

    $request = PrintRequest::create([
        'user_id' => $subject->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/purge',
    ]);

    $path = 'prints/2026/03/purge.stl';
    $photoPath = 'prints/completions/2026/03/purge-photo.webp';
    Storage::disk('local')->put($path, 'content');
    Storage::disk('local')->put($photoPath, 'photo');

    $request->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'purge.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 8,
        'sha256' => hash('sha256', 'purge'),
    ]);
    $request->completionPhotos()->create([
        'disk' => 'local',
        'path' => $photoPath,
        'original_name' => 'purge-photo.webp',
        'mime_type' => 'image/webp',
        'size_bytes' => 5,
        'width' => 1200,
        'height' => 900,
        'sort_order' => 1,
        'sha256' => hash('sha256', 'purge-photo'),
    ]);

    MagicLoginToken::create([
        'email' => $subject->email,
        'token_hash' => hash('sha256', 'purge-token'),
        'expires_at' => now()->addMinutes(10),
    ]);

    actingAs($admin);

    delete(route('admin.users.destroy', $subject))->assertRedirect();

    delete(route('admin.users.purge', ['user' => $subject->id]), [
        'confirm_email' => $subject->email,
        'confirm_purge' => true,
    ])->assertRedirect(route('admin.users.index'));

    assertDatabaseMissing('users', ['id' => $subject->id]);
    assertDatabaseMissing('print_requests', ['id' => $request->id]);
    assertDatabaseMissing('print_request_files', ['print_request_id' => $request->id]);
    assertDatabaseMissing('print_request_completion_photos', ['print_request_id' => $request->id]);
    assertDatabaseMissing('magic_login_tokens', ['email' => $subject->email]);
    expect(Storage::disk('local')->exists($path))->toBeFalse();
    expect(Storage::disk('local')->exists($photoPath))->toBeFalse();

    $event = AdminUserEvent::where('event', 'user_purged')->latest()->first();
    expect($event)->not->toBeNull()
        ->and($event?->metadata['subject']['email'] ?? null)->toBe('purge@example.com');
});

it('lets admins manage deleted and active requests from the user workspace flow', function () {
    Storage::fake('local');

    $admin = adminUser();
    $subject = User::factory()->create([
        'whitelisted_at' => now(),
    ]);

    $active = PrintRequest::create([
        'user_id' => $subject->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/active-request',
    ]);
    $deleted = PrintRequest::create([
        'user_id' => $subject->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/deleted-request',
    ]);
    $deleted->delete();

    actingAs($admin);

    get(route('admin.users.show', ['user' => $subject->id, 'lifecycle' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('requests.data', fn ($items) => count($items) === 2)
        );

    delete(route('print-requests.destroy', $active), [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    patch(route('print-requests.restore', ['print_request' => $deleted->id]), [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(['status' => 'restored']);

    $restored = $deleted->fresh();
    expect($restored?->trashed())->toBeFalse();

    delete(route('print-requests.force-destroy', ['id' => $active->id]), [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(['status' => 'force-deleted']);

    assertDatabaseMissing('print_requests', ['id' => $active->id]);
});

it('blocks magic-link requests and sign-in for soft-deleted users', function () {
    $admin = adminUser();
    $subject = User::factory()->create([
        'email' => 'deleted-login@example.com',
        'whitelisted_at' => now(),
    ]);

    actingAs($admin);
    delete(route('admin.users.destroy', $subject))->assertRedirect();

    auth()->guard('web')->logout();

    post(route('magic.send'), ['email' => $subject->email])->assertSessionHasErrors('email');

    $url = issueMagicLink($subject, $subject->email);
    get($url)->assertRedirect(route('magic.result'))->assertSessionHasErrors('email');
});
