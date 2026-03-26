<?php

use App\Enums\PrintRequestStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;

beforeEach(function () {
    // Disable absolute session enforcement and CSRF for simplicity in tests
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

it('blocks non-admins from admin status routes', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/admin1',
    ]);

    actingAs($user);

    patch(route('admin.print-requests.accept', $req))->assertForbidden();
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
    patchJson(route('admin.print-requests.accept', $req))->assertSuccessful();
    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::ACCEPTED);
    expect($req->accepted_at)->not->toBeNull();

    // Printing
    patchJson(route('admin.print-requests.printing', $req))->assertSuccessful();
    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::PRINTING);

    // Complete
    patchJson(route('admin.print-requests.complete', $req))->assertSuccessful();
    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::COMPLETE);
    expect($req->completed_at)->not->toBeNull();
});

it('stores optimized completion photos when an admin completes a request', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/admin-photos',
    ]);

    actingAs($admin);

    $firstUpload = realisticImageData(2400, 1800, 'png');
    $secondUpload = realisticImageData(2200, 1600, 'png');
    $originalSizes = [
        'finished-print.png' => strlen($firstUpload),
        'finished-print-2.png' => strlen($secondUpload),
    ];
    $expectedDimensions = [
        'finished-print.png' => [1600, 1200],
        'finished-print-2.png' => [1600, 1164],
    ];

    patch(route('admin.print-requests.complete', $req), [
        'photos' => [
            UploadedFile::fake()->createWithContent('finished-print.png', $firstUpload),
            UploadedFile::fake()->createWithContent('finished-print-2.png', $secondUpload),
        ],
        '_method' => 'patch',
    ], ['Accept' => 'application/json'])->assertSuccessful();

    $req->refresh();
    $photos = $req->completionPhotos()->get();

    expect($req->status)->toBe(PrintRequestStatus::COMPLETE)
        ->and($req->completed_at)->not->toBeNull()
        ->and($photos)->toHaveCount(2);

    foreach ($photos as $photo) {
        [$expectedWidth, $expectedHeight] = $expectedDimensions[$photo->original_name];

        expect(Storage::disk('local')->exists($photo->path))->toBeTrue()
            ->and($photo->size_bytes)->toBeGreaterThan(0)
            ->and($photo->size_bytes)->toBeLessThan($originalSizes[$photo->original_name])
            ->and($photo->mime_type)->toBeIn(['image/webp', 'image/jpeg'])
            ->and(pathinfo($photo->path, PATHINFO_EXTENSION))->toBeIn(['webp', 'jpg'])
            ->and($photo->width)->toBe($expectedWidth)
            ->and($photo->height)->toBe($expectedHeight)
            ->and(max($photo->width ?? 0, $photo->height ?? 0))->toBeLessThanOrEqual(1600);

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($photo->path));

        expect($width)->toBe($expectedWidth)
            ->and($height)->toBe($expectedHeight);
    }

    actingAs($owner);

    get(route('print-requests.completion-photos.show', ['print_request' => $req->id, 'photo' => $photos->first()->id]))
        ->assertOk()
        ->assertHeader('content-type', $photos->first()->mime_type);
});

it('validates the completion photo limit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/admin-photo-limit',
    ]);

    actingAs($admin);

    patch(route('admin.print-requests.complete', $req), [
        'photos' => [
            UploadedFile::fake()->image('1.jpg'),
            UploadedFile::fake()->image('2.jpg'),
            UploadedFile::fake()->image('3.jpg'),
            UploadedFile::fake()->image('4.jpg'),
            UploadedFile::fake()->image('5.jpg'),
            UploadedFile::fake()->image('6.jpg'),
        ],
        '_method' => 'patch',
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photos']);

    $req->refresh();

    expect($req->status)->toBe(PrintRequestStatus::PRINTING)
        ->and($req->completed_at)->toBeNull();
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

    patchJson(route('admin.print-requests.revert', $accepted))->assertSuccessful();
    $accepted->refresh();
    expect($accepted->status)->toBe(PrintRequestStatus::PENDING);
    expect($accepted->reverted_at)->not->toBeNull();

    // Start from printing
    $printing = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PRINTING,
        'source_url' => 'https://example.com/admin4',
    ]);

    patchJson(route('admin.print-requests.revert', $printing))->assertSuccessful();
    $printing->refresh();
    expect($printing->status)->toBe(PrintRequestStatus::PENDING);
    expect($printing->reverted_at)->not->toBeNull();
});

it('redirects back with a flash message for browser-based admin transitions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/admin5',
    ]);

    actingAs($admin);

    $response = $this->from(route('print-requests.show', $req))
        ->patch(route('admin.print-requests.accept', $req));

    $response->assertRedirect(route('print-requests.show', $req))
        ->assertSessionHas('status', 'Request moved to accepted.');

    $req->refresh();
    expect($req->status)->toBe(PrintRequestStatus::ACCEPTED);
});
