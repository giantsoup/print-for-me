<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows Home for guests', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
        );
});

it('does not redirect admins from home', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $response = $this->actingAs($admin)->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
        );
});

it('redirects authenticated non-admin users to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

it('renders the print-for-me favicon assets in the application shell', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('href="'.asset('favicon.svg').'?v=pfm-1"', false)
        ->assertSee('href="'.asset('favicon.png').'?v=pfm-1"', false)
        ->assertSee('href="'.asset('favicon.ico').'?v=pfm-1"', false)
        ->assertSee('href="'.asset('apple-touch-icon.png').'?v=pfm-1"', false)
        ->assertDontSee('website-logo.png', false);
});
