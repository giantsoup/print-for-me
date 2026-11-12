<?php

use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Models\PrintRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds a generic admin account and demo requests', function () {
    config(['prints.admin_email' => 'admin@example.com']);

    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Workshop Admin')
        ->and($admin->is_admin)->toBeTrue()
        ->and($admin->whitelisted_at)->not->toBeNull()
        ->and(PrintRequest::count())->toBeGreaterThan(0)
        ->and(PrintQueueItem::count())->toBeGreaterThan(0);

    $itemCount = PrintQueueItem::count();
    $plateCount = PrintQueuePlate::count();
    $this->seed(DatabaseSeeder::class);

    expect(PrintQueueItem::count())->toBe($itemCount)
        ->and(PrintQueuePlate::count())->toBe($plateCount);
});
