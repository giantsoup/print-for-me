<?php

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\PrintRequestAcceptedNotification;
use App\Notifications\PrintRequestCompletedNotification;
use App\Notifications\PrintRequestRevertedToPendingNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

beforeEach(function () {
    // Disable absolute session enforcement and CSRF for simplicity in tests
    $this->withoutMiddleware([\App\Http\Middleware\EnforceAbsoluteSession::class]);
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
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
    patch(route('admin.print-requests.accept', $req))->assertOk();

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
    patch(route('admin.print-requests.complete', $req))->assertOk();

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
    patch(route('admin.print-requests.revert', $req))->assertOk();

    Notification::assertSentTo($owner, PrintRequestRevertedToPendingNotification::class);
});
