<?php

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;

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

it('blocks non-admins from admin status routes', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/admin1',
    ]);

    actingAs($user);

    patch(route('admin.print-requests.accept', $req))->assertStatus(403);
});

it('allows admin to transition pending -> accepted -> printing -> complete', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/admin2',
    ]);

    actingAs($admin);

    // Accept
    patch(route('admin.print-requests.accept', $req))->assertOk();
    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::ACCEPTED);
    expect($req->accepted_at)->not->toBeNull();

    // Printing
    patch(route('admin.print-requests.printing', $req))->assertOk();
    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::PRINTING);

    // Complete
    patch(route('admin.print-requests.complete', $req))->assertOk();
    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::COMPLETE);
    expect($req->completed_at)->not->toBeNull();
});

it('allows admin to revert accepted or printing back to pending', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    // Start from accepted
    $accepted = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::ACCEPTED,
        'source_url' => 'https://example.com/admin3',
    ]);

    actingAs($admin);

    patch(route('admin.print-requests.revert', $accepted))->assertOk();
    $accepted->refresh();
    expect($accepted->status)->toBe(PrintRequestStatus::PENDING);
    expect($accepted->reverted_at)->not->toBeNull();

    // Start from printing
    $printing = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/admin4',
    ]);

    patch(route('admin.print-requests.revert', $printing))->assertOk();
    $printing->refresh();
    expect($printing->status)->toBe(PrintRequestStatus::PENDING);
    expect($printing->reverted_at)->not->toBeNull();
});
