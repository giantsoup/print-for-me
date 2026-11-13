<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Notification::fake();
    Log::spy();
    $this->withoutVite();
    config([
        'auth.enforce_signed_magic_in_tests' => true,
        'inertia.ssr.enabled' => false,
        'session.enforce_absolute_in_tests' => true,
        'session.enforce_version_in_tests' => true,
        'mail.default' => 'log',
    ]);
});

function issueConsoleLoginLink(string $email): string
{
    expect(Artisan::call('auth:login-link', ['email' => $email]))->toBe(0);
    preg_match('~https?://[^\s]+/magic-login\?[^\s]+~', Artisan::output(), $matches);

    return $matches[0];
}

it('prints a hashed expiring single-use link without sending or logging it', function (): void {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $url = issueConsoleLoginLink($user->email);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $token = MagicLoginToken::query()->sole();

    expect($token->token_hash)->toBe(hash('sha256', $query['token']))->not->toBe($query['token'])
        ->and($token->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue()
        ->and($token->ip)->toBe('cli')
        ->and($token->user_agent)->toBe('cli:auth:login-link');
    Notification::assertNothingSent();
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('info');

    $this->get($url)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    Auth::logout();
    $this->get($url)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
});

it('does not create an account for an unknown email', function (): void {
    $this->artisan('auth:login-link', ['email' => 'unknown@example.com'])->assertExitCode(1);
    expect(User::query()->count())->toBe(0)->and(MagicLoginToken::query()->count())->toBe(0);
});

it('refuses inactive accounts without changing their access', function (string $state): void {
    $user = User::factory()->create([
        'whitelisted_at' => $state === 'not invited' ? null : now(),
        'access_revoked_at' => $state === 'revoked' ? now() : null,
    ]);
    if ($state === 'deleted') {
        $user->delete();
    }
    $before = $user->fresh()->getAttributes();
    $this->artisan('auth:login-link', ['email' => $user->email])
        ->expectsOutput('No active, invited account matches that email. Nothing was issued.')
        ->assertExitCode(1);
    expect(MagicLoginToken::query()->count())->toBe(0)
        ->and($user->fresh()->getAttributes())->toBe($before);
})->with(['revoked', 'not invited', 'deleted']);

it('normalizes the email address', function (): void {
    User::factory()->create(['email' => 'person@example.com', 'whitelisted_at' => now()]);
    issueConsoleLoginLink('  PERSON@Example.COM ');
    expect(MagicLoginToken::query()->sole()->email)->toBe('person@example.com');
});

it('supersedes the preceding link', function (): void {
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $first = issueConsoleLoginLink($user->email);
    $second = issueConsoleLoginLink($user->email);
    $this->get($first)->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
    $this->assertGuest();
    $this->get($second)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('requires force when a real mailer is configured', function (): void {
    config(['mail.default' => 'smtp']);
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $this->artisan('auth:login-link', ['email' => $user->email])->assertExitCode(1);
    expect(MagicLoginToken::query()->count())->toBe(0);
    $this->artisan('auth:login-link', ['email' => $user->email, '--force' => true])->assertExitCode(0);
    expect(MagicLoginToken::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('uses the configured console origin', function (): void {
    config(['app.url' => 'https://print-for-me.example.ts.net']);
    URL::useOrigin(config('app.url'));
    $user = User::factory()->create(['whitelisted_at' => now()]);
    expect(issueConsoleLoginLink($user->email))->toStartWith('https://print-for-me.example.ts.net/magic-login?');
});

it('validates a console link through the loopback HTTPS proxy only when trusted', function (bool $trusted): void {
    config([
        'app.url' => 'https://print-for-me.example.ts.net',
        'trustedproxy.proxies' => $trusted ? '127.0.0.1' : null,
    ]);
    URL::useOrigin(config('app.url'));
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $url = issueConsoleLoginLink($user->email);
    $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'print-for-me.example.ts.net'])
        ->get(preg_replace('~^https:~', 'http:', $url));

    if ($trusted) {
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    } else {
        $response->assertRedirect(route('magic.result'))->assertSessionHasErrors('token');
        $this->assertGuest();
    }
})->with([true, false]);
