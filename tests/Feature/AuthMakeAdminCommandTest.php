<?php

use App\Models\User;

it('creates an admin user idempotently', function () {
    $this->artisan('auth:make-admin admin@example.com')
        ->expectsOutput('Created admin: admin@example.com')
        ->expectsOutput('Run `php artisan auth:invite admin@example.com` to send a magic login link.')
        ->assertExitCode(0);

    $user = User::where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->whitelisted_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(User::where('email', 'admin@example.com')->count())->toBe(1);

    $this->artisan('auth:make-admin admin@example.com')
        ->expectsOutput('Updated admin: admin@example.com')
        ->expectsOutput('Run `php artisan auth:invite admin@example.com` to send a magic login link.')
        ->assertExitCode(0);

    expect(User::where('email', 'admin@example.com')->count())->toBe(1);
});

it('normalizes the email before promoting so reruns stay idempotent', function () {
    $this->artisan('auth:make-admin "  ADMIN@example.com  "')
        ->expectsOutput('Created admin: admin@example.com')
        ->expectsOutput('Run `php artisan auth:invite admin@example.com` to send a magic login link.')
        ->assertExitCode(0);

    $this->artisan('auth:make-admin admin@example.com')
        ->expectsOutput('Updated admin: admin@example.com')
        ->expectsOutput('Run `php artisan auth:invite admin@example.com` to send a magic login link.')
        ->assertExitCode(0);

    $user = User::where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and(User::where('email', 'admin@example.com')->count())->toBe(1);
});

it('promotes an existing user to admin and can update the name', function () {
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'name' => 'Existing Member',
        'is_admin' => false,
        'whitelisted_at' => null,
        'email_verified_at' => null,
    ]);

    $this->artisan('auth:make-admin member@example.com --name="Workshop Admin"')
        ->expectsOutput('Updated admin: member@example.com')
        ->expectsOutput('Run `php artisan auth:invite member@example.com` to send a magic login link.')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->name)->toBe('Workshop Admin')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->whitelisted_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});
