<?php

use App\Enums\PrintRequestStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\PrintRequestAcceptedNotification;
use App\Notifications\PrintRequestCompletedNotification;
use App\Notifications\PrintRequestRevertedToPendingNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
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
