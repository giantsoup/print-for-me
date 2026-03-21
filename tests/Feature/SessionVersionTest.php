<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('writes session sv on successful magic login', function () {
    $user = User::factory()->create([
        'email' => 'sv@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
        'session_version' => 1,
    ]);

    $raw = Str::random(64); // hex not required; our controller expects size:64 arbitrary string
    $hash = hash('sha256', $raw);

    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
    ]);

    $url = URL::temporarySignedRoute('magic.login', now()->addMinutes(10), [
        'email' => $user->email,
        'token' => $raw,
    ]);

    $response = get($url);
    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('sv', 1);
});

it('logs out on next request after incrementing session_version when enforced in tests', function () {
    // Enable middleware enforcement in tests
    config(['session.enforce_version_in_tests' => true]);

    $user = User::factory()->create([
        'email' => 'sv2@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
        'session_version' => 1,
    ]);

    $raw = Str::random(64);
    $hash = hash('sha256', $raw);
    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
    ]);

    $url = URL::temporarySignedRoute('magic.login', now()->addMinutes(10), [
        'email' => $user->email,
        'token' => $raw,
    ]);

    // Log in via magic link
    get($url)->assertRedirect(route('dashboard'));

    // Increment session version via endpoint
    post(route('sessions.invalidate'))->assertRedirect();

    // Next request should be redirected by middleware
    $resp = get(route('dashboard'));
    $resp->assertRedirect(route('magic.request'));
});

it('forces mismatch via header in tests', function () {
    $user = User::factory()->create([
        'email' => 'sv3@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
        'session_version' => 1,
    ]);

    $raw = Str::random(64);
    $hash = hash('sha256', $raw);
    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
    ]);

    $url = URL::temporarySignedRoute('magic.login', now()->addMinutes(10), [
        'email' => $user->email,
        'token' => $raw,
    ]);

    get($url)->assertRedirect(route('dashboard'));

    // Force an immediate mismatch using header
    $resp = get(route('dashboard'), headers: ['X-Force-Session-Version' => '1']);
    $resp->assertRedirect(route('magic.request'));
});
