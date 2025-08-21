<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

it('allows expired-by-url but within 30s tolerance when DB token is valid', function () {
    $user = User::factory()->create([
        'email' => 'tolerance-ok@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
        'session_version' => 1,
    ]);

    $raw = Str::random(64);
    $hash = hash('sha256', $raw);

    // DB token still valid (not expired)
    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
    ]);

    // Generate a signed URL that expired 10 seconds ago (within tolerance window)
    $expiredAt = now()->subSeconds(10);
    $url = URL::temporarySignedRoute('magic.login', $expiredAt, [
        'email' => $user->email,
        'token' => $raw,
    ]);

    $response = get($url);
    $response->assertRedirect(route('dashboard'));
});

it('rejects expired-by-url beyond 30s tolerance', function () {
    $user = User::factory()->create([
        'email' => 'tolerance-fail@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
    ]);

    $raw = Str::random(64);
    $hash = hash('sha256', $raw);

    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10), // DB token valid; URL will be too old
    ]);

    // Generate a signed URL that expired 45 seconds ago (beyond tolerance)
    $expiredAt = now()->subSeconds(45);
    $url = URL::temporarySignedRoute('magic.login', $expiredAt, [
        'email' => $user->email,
        'token' => $raw,
    ]);

    $response = get($url);
    $response->assertRedirect(route('magic.result'));
    $response->assertSessionHasErrors('token');
});
