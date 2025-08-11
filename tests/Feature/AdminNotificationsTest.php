<?php

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\NewPrintRequestNotification;
use App\Notifications\PendingRequestCanceledByUserNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;

it('notifies admin on new print request creation', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    // whitelist to pass magic-link invite-only model assumption for users
    $user->whitelisted_at = now();
    $user->save();

    actingAs($user);

    $response = post(route('print-requests.store'), [
        'source_url' => 'https://example.com/new-request',
        'instructions' => 'Seeded via test',
    ], ['Accept' => 'application/json']);

    $response->assertCreated();

    $adminEmail = (string) config('prints.admin_email');

    Notification::assertSentOnDemand(NewPrintRequestNotification::class, function ($notification, $channels, $notifiable) use ($adminEmail) {
        return isset($notifiable->routes['mail']) && $notifiable->routes['mail'] === $adminEmail;
    });
});

it('notifies admin when a user cancels their pending request', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/cancel-me',
    ]);

    actingAs($user);

    $resp = delete(route('print-requests.destroy', $req), [], ['Accept' => 'application/json']);
    $resp->assertOk();

    $adminEmail = (string) config('prints.admin_email');
    Notification::assertSentOnDemand(PendingRequestCanceledByUserNotification::class, function ($notification, $channels, $notifiable) use ($adminEmail) {
        return isset($notifiable->routes['mail']) && $notifiable->routes['mail'] === $adminEmail;
    });
});
