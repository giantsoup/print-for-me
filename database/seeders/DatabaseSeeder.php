<?php

namespace Database\Seeders;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @throws Throwable
     */
    public function run(): void
    {
        $this->logInfo('Starting DatabaseSeeder: creating users and demo data...');
        $adminEmail = (string) config('prints.admin_email', 'admin@example.com');

        try {
            DB::transaction(function () use ($adminEmail) {
                // Admin user
                $admin = User::firstOrCreate(
                    ['email' => $adminEmail],
                    [
                        'name' => 'Workshop Admin',
                        'password' => Str::random(40),
                        'is_admin' => true,
                        'whitelisted_at' => now(),
                        'email_verified_at' => now(),
                    ]
                );

                // Demo users
                $demo1 = User::firstOrCreate(
                    ['email' => 'demo1@example.com'],
                    [
                        'name' => 'Demo User 1',
                        'password' => Str::random(40),
                        'whitelisted_at' => now(),
                        'email_verified_at' => now(),
                    ]
                );
                $demo2 = User::firstOrCreate(
                    ['email' => 'demo2@example.com'],
                    [
                        'name' => 'Demo User 2',
                        'password' => Str::random(40),
                        'whitelisted_at' => now(),
                        'email_verified_at' => now(),
                    ]
                );

                // Sample requests for demo1 (cover full lifecycle)
                $pending = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::PENDING,
                    'source_url' => 'https://example.com/models/pending',
                ], [
                    'instructions' => 'Seeded pending request',
                ]);

                $accepted = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::ACCEPTED,
                    'source_url' => 'https://example.com/models/accepted',
                ], [
                    'accepted_at' => now()->subDay(),
                    'instructions' => 'Seeded accepted request',
                ]);

                $printing = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::PRINTING,
                    'source_url' => 'https://example.com/models/printing',
                ], [
                    'instructions' => 'Seeded printing request',
                ]);

                $complete = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::COMPLETE,
                    'source_url' => 'https://example.com/models/complete',
                ], [
                    'completed_at' => now()->subDay(),
                    'instructions' => 'Seeded complete request',
                ]);

                // Additional robust demo data for demo1
                $revertedPending = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::PENDING,
                    'source_url' => 'https://example.com/models/reverted',
                ], [
                    'instructions' => 'Seeded reverted-to-pending request',
                    'reverted_at' => now()->subHours(12),
                ]);

                $filesOnly = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::PENDING,
                    'source_url' => null,
                ], [
                    'instructions' => 'Seeded files-only pending request',
                ]);

                $completeOld = PrintRequest::firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::COMPLETE,
                    'source_url' => 'https://example.com/models/complete-120',
                ], [
                    'completed_at' => now()->subDays(120),
                    'instructions' => 'Seeded complete > 90 days',
                ]);

                // Soft-deleted pending requests for retention flows (95 days and 83 days)
                $softDeleted95 = PrintRequest::withTrashed()->firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::PENDING,
                    'source_url' => 'https://example.com/models/pending-soft-95',
                ], [
                    'instructions' => 'Seeded soft-deleted pending (95d)',
                ]);
                $softDeleted95->forceFill(['deleted_at' => now()->subDays(95)])->save();

                $softDeleted83 = PrintRequest::withTrashed()->firstOrCreate([
                    'user_id' => $demo1->id,
                    'status' => PrintRequestStatus::PENDING,
                    'source_url' => 'https://example.com/models/pending-soft-83',
                ], [
                    'instructions' => 'Seeded soft-deleted pending (83d)',
                ]);
                $softDeleted83->forceFill(['deleted_at' => now()->subDays(83)])->save();

                // Demo2 sample spread
                $d2Pending = PrintRequest::firstOrCreate([
                    'user_id' => $demo2->id,
                    'status' => PrintRequestStatus::PENDING,
                    'source_url' => 'https://example.com/models/d2-pending',
                ], [
                    'instructions' => 'Seeded demo2 pending request',
                ]);

                $d2Accepted = PrintRequest::firstOrCreate([
                    'user_id' => $demo2->id,
                    'status' => PrintRequestStatus::ACCEPTED,
                    'source_url' => 'https://example.com/models/d2-accepted',
                ], [
                    'accepted_at' => now()->subDays(2),
                    'instructions' => 'Seeded demo2 accepted request',
                ]);

                $d2Printing = PrintRequest::firstOrCreate([
                    'user_id' => $demo2->id,
                    'status' => PrintRequestStatus::PRINTING,
                    'source_url' => 'https://example.com/models/d2-printing',
                ], [
                    'instructions' => 'Seeded demo2 printing request',
                ]);

                $d2Complete = PrintRequest::firstOrCreate([
                    'user_id' => $demo2->id,
                    'status' => PrintRequestStatus::COMPLETE,
                    'source_url' => 'https://example.com/models/d2-complete',
                ], [
                    'completed_at' => now()->subDays(1),
                    'instructions' => 'Seeded demo2 complete request',
                ]);

                // Attach demo files (idempotent by sha256)
                $this->seedFile($pending, 'seed-a.stl', 'SEED_FILE_A');
                $this->seedFile($pending, 'seed-b.obj', 'SEED_FILE_B');

                $this->seedFile($complete, 'seed-complete.stl', 'SEED_COMPLETE');
                $this->seedFile($completeOld, 'seed-very-old.stl', 'SEED_VERY_OLD');

                $this->seedFile($filesOnly, 'files-only-a.3mf', 'FILES_ONLY_A');
                $this->seedFile($filesOnly, 'files-only-b.step', 'FILES_ONLY_B');

                $this->seedFile($softDeleted95, 'seed-soft-95.obj', 'SOFT_DELETED_95');
                $this->seedFile($softDeleted83, 'seed-soft-83.stl', 'SOFT_DELETED_83');

                $this->seedFile($d2Pending, 'd2-a.stl', 'D2_FILE_A');
                $this->seedFile($d2Complete, 'd2-complete.obj', 'D2_COMPLETE');

                $this->logInfo('DatabaseSeeder complete.');
            });
        } catch (Throwable $e) {
            $this->logError('Database seeding failed.', ['exception' => $e]);
            throw $e; // rethrow so artisan signals failure
        }
    }

    private function seedFile(PrintRequest $pr, string $original, string $contents): void
    {
        try {
            $hash = hash('sha256', $contents);

            // Skip if a file with same sha256 already attached to this request
            if ($pr->files()->where('sha256', $hash)->exists()) {
                $this->logInfo("File already attached (sha256) to request #{$pr->id}: $original");

                return;
            }

            $dir = 'prints/'.now()->format('Y').'/'.now()->format('m');
            $filename = (string) Str::uuid().'.'.pathinfo($original, PATHINFO_EXTENSION);
            $path = $dir.'/'.$filename;

            $disk = Storage::disk('local');
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }

            $disk->put($path, $contents);

            $pr->files()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $original,
                'mime_type' => 'application/octet-stream',
                'size_bytes' => strlen($contents),
                'sha256' => $hash,
            ]);

            $this->logInfo("Seeded file for request #{$pr->id}: $original");
        } catch (Throwable $e) {
            $this->logWarn("Failed to seed file '$original' for request #{$pr->id}: {$e->getMessage()}");
            Log::warning('seedFile failed', [
                'request_id' => $pr->id ?? null,
                'original' => $original,
                'exception' => $e,
            ]);
        }
    }

    private function logInfo(string $message, array $context = []): void
    {
        if (property_exists($this, 'command') && $this->command) {
            $this->command->info($message);
        }
        Log::info($message, $context);
    }

    private function logWarn(string $message, array $context = []): void
    {
        if (property_exists($this, 'command') && $this->command) {
            if (method_exists($this->command, 'warn')) {
                $this->command->warn($message);
            } else {
                $this->command->line($message, 'comment');
            }
        }
        Log::warning($message, $context);
    }

    private function logError(string $message, array $context = []): void
    {
        if (property_exists($this, 'command') && $this->command) {
            $this->command->error($message);
        }
        Log::error($message, $context);
    }
}
