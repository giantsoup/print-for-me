<?php

use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use App\Notifications\PrintRequestSoftDeletedPurgeWarningNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:invite {email}', function (string $email) {
    $email = strtolower(trim($email));

    $user = User::firstOrNew(['email' => $email]);
    if (! $user->exists) {
        $user->name = strstr($email, '@', true) ?: $email;
        $user->password = Str::random(40); // Will be hashed by cast
    }
    $user->whitelisted_at = now();
    $user->save();

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    MagicLoginToken::create([
        'email' => $email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
        'ip' => 'cli',
        'user_agent' => 'cli',
    ]);

    $loginUrl = URL::temporarySignedRoute('magic.login', now()->addMinutes(10), [
        'email' => $email,
        'token' => $raw,
    ]);

    $user->notify(new MagicLoginLinkNotification($loginUrl));

    $this->info("Invited: {$email}");
    $this->line('Magic login link (10 minutes):');
    $this->line($loginUrl);
})->purpose('Invite a user by email, whitelist them, and send a magic login link.');

Artisan::command('auth:make-admin {email} {--name=}', function (string $email) {
    $email = strtolower(trim($email));
    $providedName = trim((string) $this->option('name'));

    $user = User::firstOrNew(['email' => $email]);
    $wasExistingUser = $user->exists;

    if (! $wasExistingUser) {
        $user->name = $providedName !== '' ? $providedName : (strstr($email, '@', true) ?: $email);
        $user->password = Str::random(40);
    } elseif ($providedName !== '') {
        $user->name = $providedName;
    }

    $user->forceFill([
        'is_admin' => true,
        'whitelisted_at' => $user->whitelisted_at ?? now(),
        'email_verified_at' => $user->email_verified_at ?? now(),
    ])->save();

    $action = $wasExistingUser ? 'Updated' : 'Created';

    $this->info("{$action} admin: {$email}");
    $this->line('Run `php artisan auth:invite '.$email.'` to send a magic login link.');
})->purpose('Create or promote a user to an admin account.');

Artisan::command('prints:purge-completed-files', function () {
    $threshold = now()->subDays(90);

    $totalRequests = 0;
    $deletedFiles = 0;
    $missingFiles = 0;
    $errors = 0;

    PrintRequest::whereNotNull('completed_at')
        ->where('completed_at', '<=', $threshold)
        ->with('files:id,print_request_id,disk,path')
        ->chunkById(100, function ($chunk) use (&$totalRequests, &$deletedFiles, &$missingFiles, &$errors) {
            foreach ($chunk as $pr) {
                $totalRequests++;

                foreach ($pr->files as $file) {
                    try {
                        $disk = $file->disk;
                        $path = $file->path;
                        if (Storage::disk($disk)->exists($path)) {
                            if (Storage::disk($disk)->delete($path)) {
                                $deletedFiles++;
                            } else {
                                $errors++;
                            }
                        } else {
                            $missingFiles++;
                        }
                    } catch (Throwable $e) {
                        $errors++;
                    }
                }

                // Remove file DB records to avoid dangling entries
                $pr->files()->delete();
            }
        });

    $this->info("Completed requests scanned: {$totalRequests}");
    $this->info("Files deleted: {$deletedFiles}; missing: {$missingFiles}; errors: {$errors}");
})->purpose('Purge stored files for requests completed over 90 days ago.');

Artisan::command('prints:purge-soft-deleted', function () {
    $threshold = now()->subDays(90);

    $totalRequests = 0;
    $filesDeleted = 0;
    $filesMissing = 0;
    $errors = 0;

    PrintRequest::onlyTrashed()
        ->where('deleted_at', '<=', $threshold)
        ->with('files:id,print_request_id,disk,path')
        ->chunkById(100, function ($chunk) use (&$totalRequests, &$filesDeleted, &$filesMissing, &$errors) {
            foreach ($chunk as $pr) {
                $totalRequests++;

                foreach ($pr->files as $file) {
                    try {
                        $disk = $file->disk;
                        $path = $file->path;
                        if (Storage::disk($disk)->exists($path)) {
                            if (Storage::disk($disk)->delete($path)) {
                                $filesDeleted++;
                            } else {
                                $errors++;
                            }
                        } else {
                            $filesMissing++;
                        }
                    } catch (Throwable $e) {
                        $errors++;
                    }
                }

                // Remove file DB records to avoid dangling entries
                $pr->files()->delete();
                $pr->forceDelete();
            }
        });

    $this->info("Soft-deleted requests purged: {$totalRequests}");
    $this->info("Files deleted: {$filesDeleted}; missing: {$filesMissing}; errors: {$errors}");
})->purpose('Permanently delete soft-deleted requests older than 90 days and their files.');

Artisan::command('prints:warn-soft-deleted', function () {
    $day = now()->subDays(83);
    $start = $day->copy()->startOfDay();
    $end = $day->copy()->endOfDay();

    $sent = 0;
    $skipped = 0;

    PrintRequest::onlyTrashed()
        ->whereBetween('deleted_at', [$start, $end])
        ->with('user')
        ->chunkById(100, function ($chunk) use (&$sent, &$skipped, $day) {
            foreach ($chunk as $pr) {
                $user = $pr->user;
                if (! $user) {
                    $skipped++;

                    continue;
                }

                $cacheKey = 'prints:warn-soft:'.$pr->id.':'.$day->format('Ymd');
                if (! Cache::add($cacheKey, 1, now()->addDay())) {
                    $skipped++;

                    continue;
                }

                try {
                    $user->notify(new PrintRequestSoftDeletedPurgeWarningNotification($pr));
                    $sent++;
                } catch (Throwable $e) {
                    $skipped++;
                }
            }
        });

    $this->info("Warnings sent: {$sent}; skipped: {$skipped}");
})->purpose('Warn owners 7 days before permanent purge of soft-deleted requests.');

Artisan::command('auth:cleanup-magic-tokens', function () {
    $deleted = MagicLoginToken::where('expires_at', '<', now())
        ->orWhereNotNull('used_at')
        ->delete();

    $this->info("Magic login tokens deleted: {$deleted}");
})->purpose('Cleanup expired or used magic login tokens.');

Artisan::command('auth:purge-stale-magic-tokens', function () {
    // Safety net: remove tokens older than 24 hours past their expiry time.
    $threshold = now()->subDay();
    $deleted = MagicLoginToken::where('expires_at', '<=', $threshold)->delete();

    $this->info("Stale magic login tokens deleted: {$deleted}");
})->purpose('Purge magic login tokens older than 24 hours past expiry.');

Schedule::command('auth:cleanup-magic-tokens')->hourly();
Schedule::command('auth:purge-stale-magic-tokens')->dailyAt('02:10');
Schedule::command('prints:warn-soft-deleted')->dailyAt('02:20');
Schedule::command('prints:purge-completed-files')->dailyAt('02:30');
Schedule::command('prints:purge-soft-deleted')->dailyAt('02:40');
