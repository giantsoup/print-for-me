<?php

use App\Enums\PrintRequestStatus;
use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\SourcePreviewDomain;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use App\Support\FetchPrintRequestSourcePreview;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    config([
        'session.enforce_absolute_in_tests' => true,
        'session.enforce_version_in_tests' => true,
        'auth.enforce_signed_magic_in_tests' => true,
        'inertia.ssr.enabled' => false,
    ]);
    $this->withoutVite();
});

it('shares the magic link send limit across email case variants', function () {
    $user = User::factory()->create(['email' => 'limitcase@example.com', 'whitelisted_at' => now()]);

    foreach (['limitcase', 'Limitcase', 'lImitcase', 'liMitcase', 'limItcase'] as $localPart) {
        $this->post(route('magic.send'), ['email' => $localPart.'@example.com'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    $this->post(route('magic.send'), ['email' => 'LIMITCASE@EXAMPLE.COM'])->assertTooManyRequests();
    Notification::assertSentToTimes($user, MagicLoginLinkNotification::class, 5);
    $this->assertDatabaseCount('magic_login_tokens', 5);

    $other = User::factory()->create(['email' => 'otherlimit@example.com', 'whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $other->email])->assertSessionHasNoErrors();
    Notification::assertSentToTimes($other, MagicLoginLinkNotification::class, 1);
});

it('rejects a superseded magic link immediately and accepts its replacement', function () {
    $user = User::factory()->create(['email' => 'replacement@example.com', 'whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $user->email])->assertSessionHasNoErrors();
    $firstUrl = Notification::sent($user, MagicLoginLinkNotification::class)->last()->toMail($user)->viewData['loginUrl'];
    $this->post(route('magic.send'), ['email' => $user->email])->assertSessionHasNoErrors();
    $replacementUrl = Notification::sent($user, MagicLoginLinkNotification::class)->last()->toMail($user)->viewData['loginUrl'];

    $this->get($firstUrl)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    expect(MagicLoginToken::where('email', $user->email)->whereNotNull('used_at')->count())->toBe(0);

    $this->get($replacementUrl)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(MagicLoginToken::where('email', $user->email)->whereNotNull('used_at')->count())->toBe(1);
});

it('keeps a revoked magic link unusable after access is restored', function () {
    $admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $subject = User::factory()->create(['whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $subject->email])->assertSessionHasNoErrors();
    $oldUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];

    $this->actingAs($admin)->withSession(['sv' => $admin->currentSessionVersion()]);
    $this->post(route('admin.users.access.revoke', $subject))->assertSessionHasNoErrors();
    $this->post(route('admin.users.access.restore', $subject))->assertSessionHasNoErrors();
    expect($subject->fresh()->hasActiveAccess())->toBeTrue();
    Auth::logout();

    $this->get($oldUrl)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    $this->post(route('magic.send'), ['email' => $subject->email])->assertSessionHasNoErrors();
    $newUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];
    $this->get($newUrl)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($subject);
});

it('enforces owner scope and nested file scope with live session checks', function () {
    $owner = User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]);
    $other = User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]);
    $ownRequest = PrintRequest::create(['user_id' => $owner->id, 'status' => PrintRequestStatus::PENDING, 'instructions' => 'Own request']);
    $otherRequest = PrintRequest::create(['user_id' => $other->id, 'status' => PrintRequestStatus::PENDING, 'instructions' => 'Other private request']);
    $file = $otherRequest->files()->create(['disk' => 'local', 'path' => 'audit/other.stl', 'original_name' => 'other.stl', 'size_bytes' => 12, 'sha256' => hash('sha256', 'private-file')]);
    Storage::disk('local')->put($file->path, 'private-file');

    $this->getJson(route('print-requests.show', $ownRequest))->assertUnauthorized();
    $this->actingAs($owner)->withSession(['sv' => $owner->currentSessionVersion()]);
    $this->getJson(route('print-requests.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownRequest->id);
    $this->getJson(route('print-requests.show', $otherRequest))->assertForbidden()->assertDontSee('Other private request');
    $this->get(route('print-requests.files.download', [$ownRequest, $file]))->assertNotFound()->assertDontSee('private-file');
    $this->patchJson(route('print-requests.update', $otherRequest), ['instructions' => 'Changed'])->assertForbidden();
    expect($otherRequest->fresh()->instructions)->toBe('Other private request');
    Storage::disk('local')->assertExists($file->path);

    $this->actingAs($other)->withSession(['sv' => $other->currentSessionVersion()]);
    $this->get(route('print-requests.files.download', [$otherRequest, $file]))->assertDownload('other.stl')->assertStreamedContent('private-file');
});

it('does not authenticate a token consumed after it was selected', function () {
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $user->email])->assertSessionHasNoErrors();
    $url = Notification::sent($user, MagicLoginLinkNotification::class)->last()->toMail($user)->viewData['loginUrl'];
    $token = MagicLoginToken::where('email', $user->email)->firstOrFail();
    $consumedAt = now()->subSecond()->startOfSecond();

    User::retrieved(function (User $retrieved) use ($user, $token, $consumedAt): void {
        if ($retrieved->id === $user->id) {
            MagicLoginToken::whereKey($token->id)->update(['used_at' => $consumedAt]);
        }
    });

    try {
        $this->get($url)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
        $this->assertGuest();
        expect($token->fresh()->used_at->equalTo($consumedAt))->toBeTrue();
    } finally {
        Event::forget('eloquent.retrieved: '.User::class);
    }
});

it('invalidates links still within clock skew grace when an admin reinvites a member', function () {
    $admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $subject = User::factory()->create(['whitelisted_at' => now()]);
    $raw = str_repeat('a', 64);
    $old = MagicLoginToken::create(['email' => $subject->email, 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->subSeconds(5)]);
    $oldUrl = URL::temporarySignedRoute('magic.login', now()->subSeconds(5), ['email' => $subject->email, 'token' => $raw]);

    $this->actingAs($admin)->withSession(['sv' => $admin->currentSessionVersion()]);
    $this->post(route('admin.users.invite', $subject))->assertSessionHasNoErrors();
    $newUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];
    Auth::logout();

    $this->get($oldUrl)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    expect($old->fresh()->used_at)->toBeNull();
    $this->get($newUrl)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($subject);
});

it('does not revive an old link if an admin changes an email away and back', function () {
    $admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $subject = User::factory()->create(['email' => 'original@example.com', 'whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $subject->email])->assertSessionHasNoErrors();
    $oldUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];

    $this->actingAs($admin)->withSession(['sv' => $admin->currentSessionVersion()]);
    $this->patch(route('admin.users.update', $subject), ['name' => $subject->name, 'email' => 'temporary@example.com'])->assertSessionHasNoErrors();
    $this->patch(route('admin.users.update', $subject), ['name' => $subject->name, 'email' => 'original@example.com'])->assertSessionHasNoErrors();
    $this->post(route('admin.users.access.restore', $subject))->assertSessionHasNoErrors();
    Auth::logout();

    $this->get($oldUrl)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    $this->post(route('magic.send'), ['email' => $subject->email])->assertSessionHasNoErrors();
    $newUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];
    $this->get($newUrl)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($subject);
});

it('rejects unsigned private storage reads and writes', function () {
    Storage::disk('local')->put('audit/capability.txt', 'synthetic-private-content');
    $this->get('/storage/audit/capability.txt')->assertForbidden()->assertDontSee('synthetic-private-content');
    $this->put('/storage/audit/capability.txt', ['replacement' => 'changed'])->assertForbidden();
    expect(Storage::disk('local')->get('audit/capability.txt'))->toBe('synthetic-private-content');

    $url = URL::temporarySignedRoute('storage.local', now()->addMinute(), ['path' => 'audit/capability.txt'], absolute: false);
    $this->get($url)->assertOk()->assertStreamedContent('synthetic-private-content');
});

it('blocks unprivileged actors from every administrative action without side effects', function (string $actor) {
    $member = User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]);
    $subject = User::factory()->create(['whitelisted_at' => now()]);
    $printRequest = PrintRequest::create(['user_id' => $subject->id, 'status' => PrintRequestStatus::PENDING]);
    $domain = SourcePreviewDomain::factory()->create();
    if ($actor === 'member') {
        $this->actingAs($member)->withSession(['sv' => $member->currentSessionVersion()]);
    }

    $actions = [
        ['GET', 'admin.invite.create', []], ['POST', 'admin.invite.store', []],
        ['GET', 'admin.users.index', []], ['GET', 'admin.users.show', [$subject]],
        ['PATCH', 'admin.users.update', [$subject]], ['DELETE', 'admin.users.destroy', [$subject]],
        ['POST', 'admin.users.invite', [$subject]], ['POST', 'admin.users.access.revoke', [$subject]],
        ['POST', 'admin.users.access.restore', [$subject]], ['POST', 'admin.users.sessions.invalidate', [$subject]],
        ['POST', 'admin.users.role.promote', [$subject]], ['POST', 'admin.users.role.demote', [$subject]],
        ['POST', 'admin.users.restore', [$subject]], ['DELETE', 'admin.users.purge', [$subject]],
        ['PATCH', 'admin.print-requests.accept', [$printRequest]], ['PATCH', 'admin.print-requests.printing', [$printRequest]],
        ['PATCH', 'admin.print-requests.complete', [$printRequest]], ['PATCH', 'admin.print-requests.revert', [$printRequest]],
        ['POST', 'admin.print-requests.notifications.completed.resend', [$printRequest]],
        ['POST', 'admin.print-requests.notifications.completed.send-test', [$printRequest]],
        ['POST', 'admin.print-requests.source-preview.refetch', [$printRequest]],
        ['GET', 'admin.source-preview-domains.index', []],
        ['PATCH', 'admin.source-preview-domains.update', [$domain]],
        ['POST', 'admin.source-preview-domains.attempt', [$domain]],
        ['POST', 'admin.source-preview-domains.attempt-url', [$domain]],
    ];

    foreach ($actions as [$method, $name, $parameters]) {
        $this->json($method, route($name, $parameters), [
            'name' => 'Unauthorized update', 'email' => 'unauthorized@example.com',
            'confirm_email' => $subject->email, 'confirm_purge' => true, 'policy' => 'allow', 'url' => 'https://example.com/model',
        ])->assertStatus($actor === 'member' ? 403 : 401);
    }

    expect($subject->fresh()->hasActiveAccess())->toBeTrue()
        ->and($subject->fresh()->is_admin)->toBeFalse()
        ->and($printRequest->fresh()->status)->toBe(PrintRequestStatus::PENDING);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('admin_user_events', 0);
    $this->assertDatabaseCount('magic_login_tokens', 0);
    Notification::assertNothingSent();
    Http::assertNothingSent();
})->with(['guest', 'member']);

it('blocks unexpected outbound HTTP in the test application', function () {
    expect(fn () => Http::get('https://unexpected.example.test/audit'))->toThrow(StrayRequestException::class);
});

it('invalidates the previous magic link for every invitation entry point', function (string $entryPoint) {
    $subject = User::factory()->create(['whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $subject->email])->assertSessionHasNoErrors();
    $oldUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];

    if ($entryPoint === 'console') {
        $this->artisan('auth:invite', ['email' => $subject->email])->assertExitCode(0);
    } else {
        $admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
        $this->actingAs($admin)->withSession(['sv' => $admin->currentSessionVersion()]);
        $this->post(route('admin.invite.store'), ['email' => $subject->email])->assertSessionHasNoErrors();
        Auth::logout();
    }

    $newUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];
    $this->get($oldUrl)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    $this->get($newUrl)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($subject);
})->with(['console', 'admin']);

it('keeps another owners files when removal IDs mix request scopes', function () {
    Queue::fake([FetchPrintRequestSourcePreview::class]);
    $owner = User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]);
    $other = User::factory()->create();
    $ownRequest = PrintRequest::create(['user_id' => $owner->id, 'status' => PrintRequestStatus::PENDING, 'source_url' => 'https://example.com/model']);
    $otherRequest = PrintRequest::create(['user_id' => $other->id, 'status' => PrintRequestStatus::PENDING]);
    $attributes = ['disk' => 'local', 'original_name' => 'part.stl', 'size_bytes' => 4, 'sha256' => hash('sha256', 'part')];
    $ownFile = $ownRequest->files()->create([...$attributes, 'path' => 'audit/own.stl']);
    $otherFile = $otherRequest->files()->create([...$attributes, 'path' => 'audit/other.stl']);
    Storage::disk('local')->put($ownFile->path, 'part');
    Storage::disk('local')->put($otherFile->path, 'part');
    $this->actingAs($owner)->withSession(['sv' => $owner->currentSessionVersion()]);

    $this->patchJson(route('print-requests.update', $ownRequest), ['remove_file_ids' => [$ownFile->id, $otherFile->id]])->assertOk();
    $this->assertModelMissing($ownFile);
    $this->assertModelExists($otherFile);
    Storage::disk('local')->assertMissing($ownFile->path);
    Storage::disk('local')->assertExists($otherFile->path);
});

it('checks photo parent scope and allows the owning member to read it', function () {
    $owner = User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]);
    $other = User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]);
    $ownRequest = PrintRequest::create(['user_id' => $owner->id, 'status' => PrintRequestStatus::COMPLETE]);
    $otherRequest = PrintRequest::create(['user_id' => $other->id, 'status' => PrintRequestStatus::COMPLETE]);
    $photo = $otherRequest->completionPhotos()->create(['disk' => 'local', 'path' => 'audit/photo.webp', 'original_name' => 'photo.webp', 'mime_type' => 'image/webp', 'size_bytes' => 15, 'sha256' => hash('sha256', 'synthetic-photo')]);
    Storage::disk('local')->put($photo->path, 'synthetic-photo');
    $this->actingAs($owner)->withSession(['sv' => $owner->currentSessionVersion()]);

    $this->get(route('print-requests.completion-photos.show', [$ownRequest, $photo]))->assertNotFound()->assertDontSee('synthetic-photo');
    $this->get(route('print-requests.completion-photos.show', [$otherRequest, $photo]))->assertForbidden()->assertDontSee('synthetic-photo');
    $this->actingAs($other)->withSession(['sv' => $other->currentSessionVersion()]);
    $this->get(route('print-requests.completion-photos.show', [$otherRequest, $photo]))->assertOk()->assertStreamedContent('synthetic-photo');
});

it('allows exactly one sign in with an issued magic link', function () {
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $this->post(route('magic.send'), ['email' => $user->email])->assertSessionHasNoErrors();
    $url = Notification::sent($user, MagicLoginLinkNotification::class)->last()->toMail($user)->viewData['loginUrl'];

    $this->get($url)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $token = MagicLoginToken::where('email', $user->email)->firstOrFail();
    expect($token->used_at)->not->toBeNull();
    Auth::logout();

    $this->get($url)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    expect($token->fresh()->used_at->equalTo($token->used_at))->toBeTrue();
});
