<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('users promoted with the make admin command receive admin dashboard props', function () {
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'is_admin' => false,
    ]);

    $this->artisan('auth:make-admin member@example.com')
        ->expectsOutput('Updated admin: member@example.com')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->is_admin)->toBeTrue();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdmin', true)
            ->where('auth.user.email', 'member@example.com')
            ->where('auth.user.is_admin', true)
        );
});
