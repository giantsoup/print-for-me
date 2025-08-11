<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;

use function Pest\Laravel\get;

beforeEach(function () {
    // Disable throttling and signature validation for this test file
    $this->withoutMiddleware([ThrottleRequests::class, ValidateSignature::class]);
});

it('rejects expired magic login tokens', function () {
    $user = User::factory()->create([
        'email' => 'expired@example.com',
        'email_verified_at' => now(),
        'whitelisted_at' => now(),
    ]);

    // Create an expired token
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    MagicLoginToken::create([
        'email' => $user->email,
        'token_hash' => $hash,
        'expires_at' => now()->subMinute(), // already expired
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    $response = get(route('magic.login', ['email' => $user->email, 'token' => $raw]));

    $response->assertRedirect(route('magic.result'));
    $response->assertSessionHasErrors('token');
});
