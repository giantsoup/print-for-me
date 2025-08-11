<?php

namespace Database\Seeders;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Taylor Oyer',
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

        // Sample requests for demo1
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

        // Stub a couple of files for the pending request
        $this->seedFile($pending, 'seed-a.stl', 'SEED_FILE_A');
        $this->seedFile($pending, 'seed-b.obj', 'SEED_FILE_B');

        // One file for the complete request
        $this->seedFile($complete, 'seed-complete.stl', 'SEED_COMPLETE');
    }

    private function seedFile(PrintRequest $pr, string $original, string $contents): void
    {
        $dir = 'prints/' . now()->format('Y') . '/' . now()->format('m');
        $filename = (string) Str::uuid() . '.' . pathinfo($original, PATHINFO_EXTENSION);
        $path = $dir . '/' . $filename;

        Storage::disk('local')->put($path, $contents);
        $pr->files()->firstOrCreate([
            'path' => $path,
        ], [
            'disk' => 'local',
            'original_name' => $original,
            'mime_type' => 'application/octet-stream',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ]);
    }
}
