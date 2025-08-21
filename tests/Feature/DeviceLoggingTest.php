<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Disable route throttling and signature validation for login in these tests
    $this->withoutMiddleware([ThrottleRequests::class, ValidateSignature::class]);
});

it('records last login IP and user agent on successful magic login', function () {
    $user = User::factory()->create([
        'email' => 'device@example.com',
    ]);
    $user->forceFill(['whitelisted_at' => now()])->save();

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
        'ip' => '127.0.0.1',
        'user_agent' => 'Setup',
    ]);

    $ip = '203.0.113.10';
    $ua = 'Pest UA';

    $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeader('User-Agent', $ua)
        ->get(route('magic.login', ['email' => $user->email, 'token' => $raw]));

    $response->assertRedirect(route('dashboard'));

    $fresh = $user->fresh();
    expect($fresh->last_login_ip)->toBe($ip);
    expect($fresh->last_login_user_agent)->toBe($ua);
});

it('exposes last login device fields on profile inertia response', function () {
    $user = User::factory()->create([
        'email' => 'profile-device@example.com',
        'last_login_ip' => '198.51.100.22',
        'last_login_user_agent' => 'UnitTest UA',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.last_login_ip', '198.51.100.22')
            ->where('auth.user.last_login_user_agent', 'UnitTest UA')
        );
});
