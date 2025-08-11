<?php

use App\Enums\PrintRequestStatus;
use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\PrintRequestSoftDeletedPurgeWarningNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

it('purges files for completed requests older than 90 days', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $pr = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/completed-old',
        'completed_at' => now()->subDays(95),
    ]);

    $path = 'prints/2025/08/old-complete.stl';
    Storage::disk('local')->put($path, 'OLD COMPLETE');
    $pr->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'old-complete.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 10,
        'sha256' => hash('sha256', 'OLD COMPLETE'),
    ]);

    artisan('prints:purge-completed-files');

    expect(Storage::disk('local')->exists($path))->toBeFalse();
    expect($pr->files()->count())->toBe(0);
});

it('permanently deletes soft-deleted requests older than 90 days and their files', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $pr = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/soft-old',
    ]);

    $path = 'prints/2025/08/soft-old.stl';
    Storage::disk('local')->put($path, 'SOFT OLD');
    $pr->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'soft-old.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 10,
        'sha256' => hash('sha256', 'SOFT OLD'),
    ]);

    $pr->delete();
    $pr->forceFill(['deleted_at' => now()->subDays(95)])->save();

    artisan('prints:purge-soft-deleted');

    assertDatabaseMissing('print_requests', ['id' => $pr->id]);
    assertDatabaseMissing('print_request_files', ['print_request_id' => $pr->id]);
    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

it('warns owners 7 days before permanent purge of soft-deleted requests', function () {
    Notification::fake();

    $user = User::factory()->create();

    $pr = PrintRequest::create([
        'user_id' => $user->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/soft-warn',
    ]);

    $pr->delete();
    $deletedAt = now()->subDays(83)->startOfDay()->addHours(12);
    $pr->forceFill(['deleted_at' => $deletedAt])->save();

    artisan('prints:warn-soft-deleted');

    Notification::assertSentTo($user, PrintRequestSoftDeletedPurgeWarningNotification::class);
});

it('cleans up expired or used magic login tokens', function () {
    $email = 'clean@example.com';

    // expired
    MagicLoginToken::create([
        'email' => $email,
        'token_hash' => hash('sha256', 'A'),
        'expires_at' => now()->subMinute(),
        'ip' => 't',
        'user_agent' => 't',
    ]);

    // used
    MagicLoginToken::create([
        'email' => $email,
        'token_hash' => hash('sha256', 'B'),
        'expires_at' => now()->addMinute(),
        'used_at' => now(),
        'ip' => 't',
        'user_agent' => 't',
    ]);

    // valid
    $valid = MagicLoginToken::create([
        'email' => $email,
        'token_hash' => hash('sha256', 'C'),
        'expires_at' => now()->addMinutes(10),
        'ip' => 't',
        'user_agent' => 't',
    ]);

    artisan('auth:cleanup-magic-tokens');

    assertDatabaseMissing('magic_login_tokens', ['token_hash' => hash('sha256', 'A')]);
    assertDatabaseMissing('magic_login_tokens', ['token_hash' => hash('sha256', 'B')]);
    assertDatabaseHas('magic_login_tokens', ['token_hash' => $valid->token_hash]);
});
