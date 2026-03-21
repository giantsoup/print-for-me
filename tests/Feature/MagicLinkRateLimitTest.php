<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('rate limits magic link requests per email and IP (5/hour, 429 on 6th)', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'limit@example.com',
    ]);
    $user->forceFill(['whitelisted_at' => now()])->save();

    // First 5 requests allowed
    for ($i = 0; $i < 5; $i++) {
        $resp = $this->post(route('magic.send'), [
            'email' => 'limit@example.com',
        ]);
        $resp->assertRedirect();
    }

    // Sixth request should be throttled
    $resp = $this->post(route('magic.send'), [
        'email' => 'limit@example.com',
    ]);

    $resp->assertTooManyRequests();

    // Follow redirect to the request page and ensure friendly message is present in Inertia props
    $follow = $this->get(route('magic.request'));
    $follow->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/RequestMagicLink')
            ->where('errors.session.0', 'Too many requests. Please wait a bit before trying again.')
        );
});
