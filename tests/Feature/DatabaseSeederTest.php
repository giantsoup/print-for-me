<?php

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
        ->and(PrintRequest::count())->toBeGreaterThan(0);
});
