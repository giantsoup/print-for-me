<?php

namespace Tests\Feature;

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class MagicTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_new_token_expires_prior_unused_unexpired_tokens(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'cycle@example.com',
            'whitelisted_at' => now(),
        ]);

        // Issue first token
        $this->post(route('magic.send'), ['email' => $user->email])->assertSessionHasNoErrors();
        $first = MagicLoginToken::where('email', $user->email)->firstOrFail();
        $this->assertTrue($first->expires_at->gt(now()->addMinutes(-9))); // sanity: initially ~+10m

        // Issue second token, which should soft-expire the first one
        $this->post(route('magic.send'), ['email' => $user->email])->assertSessionHasNoErrors();

        $first->refresh();
        $this->assertNull($first->used_at);
        $this->assertTrue($first->expires_at->lte(now()));

        $this->assertDatabaseCount('magic_login_tokens', 2);
    }

    public function test_unique_index_on_email_and_token_hash_is_enforced(): void
    {
        $hash = hash('sha256', 'same-raw');

        MagicLoginToken::create([
            'email' => 'dup@example.com',
            'token_hash' => $hash,
            'expires_at' => now()->addMinutes(10),
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->expectException(QueryException::class);

        MagicLoginToken::create([
            'email' => 'dup@example.com',
            'token_hash' => $hash, // duplicate
            'expires_at' => now()->addMinutes(10),
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
    }

    public function test_purge_stale_magic_tokens_command_deletes_expected_rows(): void
    {
        // Older than 24h past expiry -> delete
        $tooOld = MagicLoginToken::create([
            'email' => 'a@example.com',
            'token_hash' => hash('sha256', 'A'),
            'expires_at' => now()->subHours(25),
        ]);

        // Exactly 24h past expiry -> delete
        $edgeOld = MagicLoginToken::create([
            'email' => 'b@example.com',
            'token_hash' => hash('sha256', 'B'),
            'expires_at' => now()->subDay(),
        ]);

        // 23h past expiry -> keep
        $keepRecentExpired = MagicLoginToken::create([
            'email' => 'c@example.com',
            'token_hash' => hash('sha256', 'C'),
            'expires_at' => now()->subHours(23),
        ]);

        // Future expiry -> keep
        $valid = MagicLoginToken::create([
            'email' => 'd@example.com',
            'token_hash' => hash('sha256', 'D'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('auth:purge-stale-magic-tokens')->assertExitCode(0);

        $this->assertDatabaseMissing('magic_login_tokens', ['token_hash' => $tooOld->token_hash]);
        $this->assertDatabaseMissing('magic_login_tokens', ['token_hash' => $edgeOld->token_hash]);
        $this->assertDatabaseHas('magic_login_tokens', ['token_hash' => $keepRecentExpired->token_hash]);
        $this->assertDatabaseHas('magic_login_tokens', ['token_hash' => $valid->token_hash]);
    }
}
