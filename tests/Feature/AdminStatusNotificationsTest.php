<?php

use App\Enums\PrintRequestStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\PrintRequestAcceptedNotification;
use App\Notifications\PrintRequestCompletedNotification;
use App\Notifications\PrintRequestRevertedToPendingNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    // Disable absolute session enforcement and CSRF for simplicity in tests
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

it('sends notification to requester when admin accepts the request', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/accept-me',
    ]);

    actingAs($admin);
    patchJson(route('admin.print-requests.accept', $req))->assertSuccessful();

    Notification::assertSentTo($owner, PrintRequestAcceptedNotification::class);
});

it('sends notification to requester when admin completes the request', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/complete-me',
    ]);

    actingAs($admin);
    patchJson(route('admin.print-requests.complete', $req))->assertSuccessful();

    Notification::assertSentTo($owner, PrintRequestCompletedNotification::class);
});

it('logs completion email dispatch context when debug logging is enabled', function () {
    Storage::fake('local');
    Notification::fake();
    Log::spy();

    config(['prints.log_completion_email_debug' => true]);

    $admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@example.com',
    ]);
    $owner = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/complete-log-debug',
    ]);

    actingAs($admin);

    patch(route('admin.print-requests.complete', $req), [
        'photos' => [
            UploadedFile::fake()->createWithContent('finished-print.png', realisticImageData(2400, 1800, 'png')),
        ],
        '_method' => 'patch',
    ], ['Accept' => 'application/json'])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'completion_email.notification_dispatch_requested'
            && $context['delivery_mode'] === 'initial_requester_delivery'
            && $context['completion_photo_count'] === 1
            && $context['recipient_email'] === 'owner@example.com'
            && $context['actor_email'] === 'admin@example.com';
    })->once();
});

it('embeds a completion preview image in the outgoing completion email after an admin completes a request with photos', function () {
    Storage::fake('local');

    config([
        'mail.default' => 'array',
        'queue.default' => 'sync',
    ]);

    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');

    $mailer = app('mail.manager')->mailer('array');
    $transport = $mailer->getSymfonyTransport();

    if (method_exists($transport, 'flush')) {
        $transport->flush();
    }

    $admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@example.com',
    ]);
    $owner = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/complete-with-photo',
    ]);

    actingAs($admin);

    patch(route('admin.print-requests.complete', $req), [
        'photos' => [
            UploadedFile::fake()->createWithContent('finished-print.png', realisticImageData(2400, 1800, 'png')),
        ],
        '_method' => 'patch',
    ], ['Accept' => 'application/json'])->assertSuccessful();

    $messages = $transport->messages();

    expect($messages)->toHaveCount(1);

    $message = $messages->last()->getOriginalMessage();
    $html = (string) $message->getHtmlBody();
    $raw = $message->toString();

    expect($html)
        ->toContain('Completion preview')
        ->toContain('src="cid:')
        ->and($raw)->toContain('Content-Type: multipart/related;')
        ->toContain('Content-Disposition: inline;')
        ->toContain('Content-Type: image/jpeg;');
});

it('sends notification to requester when admin reverts to pending', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::ACCEPTED,
        'source_url' => 'https://example.com/revert-me',
    ]);

    actingAs($admin);
    patchJson(route('admin.print-requests.revert', $req))->assertSuccessful();

    Notification::assertSentTo($owner, PrintRequestRevertedToPendingNotification::class);
});

it('allows an admin to resend the completion notification for a completed request', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/resend-complete',
        'completed_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.print-requests.notifications.completed.resend', $req))
        ->assertRedirect()
        ->assertSessionHas('status', 'Completion email queued again.');

    Notification::assertSentTo($owner, PrintRequestCompletedNotification::class);
});

it('does not resend the completion notification for a request that is not complete', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/resend-invalid',
    ]);

    actingAs($admin);

    postJson(route('admin.print-requests.notifications.completed.resend', $req))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    Notification::assertNothingSent();
});

it('allows an admin to send the completion notification preview to their own inbox', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/preview-complete',
        'completed_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.print-requests.notifications.completed.send-test', $req))
        ->assertRedirect()
        ->assertSessionHas('status', 'Completion email preview queued to your inbox.');

    Notification::assertSentTo($admin, PrintRequestCompletedNotification::class);
    Notification::assertNotSentTo($owner, PrintRequestCompletedNotification::class);
});

it('does not send the completion preview email for a request that is not complete', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/preview-invalid',
    ]);

    actingAs($admin);

    postJson(route('admin.print-requests.notifications.completed.send-test', $req))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    Notification::assertNothingSent();
});
