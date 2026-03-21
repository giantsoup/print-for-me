<?php

use App\Enums\PrintRequestStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;

beforeEach(function () {
    // Disable absolute session enforcement and CSRF for simplicity in tests
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

it('allows owner to permanently delete their soft-deleted pending request and removes associated files', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/remove',
    ]);

    // Attach a file record and the underlying storage file
    $path = 'prints/2025/08/remove.stl';
    Storage::disk('local')->put($path, '3D FILE');

    $req->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'remove.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 10,
        'sha256' => hash('sha256', 'to-remove'),
    ]);

    actingAs($user);

    // Soft delete first (only allowed while pending)
    delete(route('print-requests.destroy', $req), [], ['Accept' => 'application/json'])->assertOk();

    // Now force delete via the new endpoint
    $resp = delete(route('print-requests.force-destroy', ['id' => $req->id]), [], ['Accept' => 'application/json']);
    $resp->assertOk()->assertJson(['status' => 'force-deleted']);

    // The print request and its files should be gone from the database
    assertDatabaseMissing('print_requests', ['id' => $req->id]);
    assertDatabaseMissing('print_request_files', ['print_request_id' => $req->id]);

    // The underlying storage file should be deleted
    expect(Storage::disk('local')->exists($path))->toBeFalse();
});
