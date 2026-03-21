<?php

use App\Enums\PrintRequestStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    // Disable the absolute session middleware to avoid forced re-login in tests
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    // CSRF is not relevant in tests.
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

it('creates a print request with only a source URL', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = post(route('print-requests.store'), [
        'source_url' => 'https://example.com/models/123',
        'instructions' => 'Please print at 0.2mm layer height.',
    ], ['Accept' => 'application/json']);

    $response->assertCreated();
    expect($response->json('user_id'))->toBe($user->id);
    expect($response->json('source_url'))->toBe('https://example.com/models/123');
    expect($response->json('files'))->toBe([]);

    assertDatabaseHas('print_requests', [
        'user_id' => $user->id,
        'source_url' => 'https://example.com/models/123',
        'status' => PrintRequestStatus::PENDING,
    ]);
});

it('requires at least one source (url or file) on create', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = post(route('print-requests.store'), [], ['Accept' => 'application/json']);

    $response->assertStatus(422)->assertJsonValidationErrors(['files']);
});

it('rejects disallowed file extensions', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    actingAs($user);

    $file = UploadedFile::fake()->create('notes.txt', 10);

    $response = post(route('print-requests.store'), [
        'files' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)->assertJsonValidationErrors(['files.0']);
});

it('rejects a file larger than 50 MB', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    actingAs($user);

    $big = UploadedFile::fake()->create('model.stl', 60000); // ~60 MB

    $response = post(route('print-requests.store'), [
        'files' => [$big],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)->assertJsonValidationErrors(['files.0']);
});

it('rejects when total uploaded size exceeds 50 MB', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    actingAs($user);

    $f1 = UploadedFile::fake()->create('a.stl', 25000);
    $f2 = UploadedFile::fake()->create('b.stl', 25000);
    $f3 = UploadedFile::fake()->create('c.stl', 2000); // total 52000 KB

    $response = post(route('print-requests.store'), [
        'files' => [$f1, $f2, $f3],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)->assertJsonValidationErrors(['files']);
});

it('rejects when more than 10 files are uploaded', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    actingAs($user);

    $files = [];
    for ($i = 0; $i < 11; $i++) {
        $files[] = UploadedFile::fake()->create("f{$i}.stl", 10);
    }

    $response = post(route('print-requests.store'), [
        'files' => $files,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)->assertJsonValidationErrors(['files']);
});

it('lists only the authenticated user\'s print requests (non-admin)', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    // Create 2 for this user, 1 for another
    PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/1',
    ]);
    PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/2',
    ]);
    PrintRequest::create([
        'user_id' => $other->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/3',
    ]);

    actingAs($user);

    $response = get(route('print-requests.index'), ['Accept' => 'application/json']);

    $response->assertOk();
    $data = $response->json('data');
    expect(collect($data)->every(fn ($row) => $row['user_id'] === $user->id))->toBeTrue();
    expect(count($data))->toBe(2);
});

it('prevents updating a non-pending request for non-admin users', function () {
    $user = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::ACCEPTED,
        'source_url' => 'https://example.com/x',
    ]);

    actingAs($user);

    $response = patch(route('print-requests.update', $req), [
        'instructions' => 'Change infill to 20%',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(403);
});

it('allows owner to soft delete a pending request', function () {
    $user = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/y',
    ]);

    actingAs($user);

    $response = delete(route('print-requests.destroy', $req), [], ['Accept' => 'application/json']);
    $response->assertOk();

    $trashed = PrintRequest::withTrashed()->find($req->id);
    expect($trashed->trashed())->toBeTrue();
});

it('can add and remove files on update while pending, and deduplicates same content', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $req = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/z',
    ]);

    // Seed with two existing files
    $fA = $req->files()->create([
        'disk' => 'local',
        'path' => 'prints/2025/08/a.stl',
        'original_name' => 'a.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 1024,
        'sha256' => hash('sha256', 'A'),
    ]);
    $fB = $req->files()->create([
        'disk' => 'local',
        'path' => 'prints/2025/08/b.stl',
        'original_name' => 'b.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 2048,
        'sha256' => hash('sha256', 'B'),
    ]);

    // Two uploads with identical content should result in one attachment
    $dup1 = UploadedFile::fake()->createWithContent('dup.stl', 'SAME_CONTENT');
    $dup2 = UploadedFile::fake()->createWithContent('dup2.stl', 'SAME_CONTENT');

    actingAs($user);

    $response = patch(route('print-requests.update', $req), [
        'remove_file_ids' => [$fA->id],
        'files' => [$dup1, $dup2],
    ], ['Accept' => 'application/json']);

    $response->assertOk();

    $req->refresh();
    $files = $req->files;

    // Expect: removed one, added one (deduped) => total 2
    expect($files)->toHaveCount(2);

    // Ensure only one file with the dup hash exists
    $dupHash = hash('sha256', 'SAME_CONTENT');
    expect($files->where('sha256', $dupHash)->count())->toBe(1);
});

it('allows owner to securely download their file and blocks non-owner', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $req = PrintRequest::create([
        'user_id' => $owner->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/dl',
    ]);

    $file = $req->files()->create([
        'disk' => 'local',
        'path' => 'prints/2025/08/download.stl',
        'original_name' => 'download.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 10,
        'sha256' => hash('sha256', 'download-file'),
    ]);

    // Put the file into fake storage
    Storage::disk('local')->put($file->path, '3D FILE');

    // Owner can download
    actingAs($owner);
    $ok = get(route('print-requests.files.download', [$req, $file]));
    $ok->assertOk();
    $disposition = $ok->headers->get('content-disposition');
    expect(strtolower($disposition))->toContain('attachment');
    expect($disposition)->toContain('download.stl');

    // Non-owner blocked
    actingAs($other);
    $forbidden = get(route('print-requests.files.download', [$req, $file]));
    $forbidden->assertStatus(403);
});
