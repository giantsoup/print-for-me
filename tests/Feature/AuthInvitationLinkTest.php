<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use App\Services\AdminUsers\ReinviteUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo(now()->startOfSecond());
});

it('issues hash-only expiring invitations and invalidates earlier links', function (string $entryPoint, ?string $userAgent, string $expectedAgent): void {
    $subject = User::factory()->create(['whitelisted_at' => null]);
    $actor = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now()]);
    $issue = function () use ($entryPoint, $subject, $actor, $userAgent): void {
        if ($entryPoint === 'console') {
            $this->artisan('auth:invite', ['email' => $subject->email])->assertExitCode(0);
        } else {
            app(ReinviteUser::class)($actor, $subject, '192.0.2.12', $userAgent);
        }
    };

    $issue();
    $first = MagicLoginToken::query()->sole();
    $firstUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];
    parse_str(parse_url($firstUrl, PHP_URL_QUERY), $firstQuery);

    expect($first->email)->toBe($subject->email)
        ->and($first->token_hash)->toBe(hash('sha256', $firstQuery['token']))->not->toBe($firstQuery['token'])
        ->and($first->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue()
        ->and($firstQuery['expires'])->toBe((string) now()->addMinutes(10)->timestamp)
        ->and($first->used_at)->toBeNull()
        ->and($first->ip)->toBe($entryPoint === 'console' ? 'cli' : '192.0.2.12')
        ->and($first->user_agent)->toBe($expectedAgent)
        ->and($subject->fresh()->whitelisted_at)->not->toBeNull();

    $issue();
    $replacement = MagicLoginToken::query()->whereKeyNot($first->id)->sole();
    $replacementUrl = Notification::sent($subject, MagicLoginLinkNotification::class)->last()->toMail($subject)->viewData['loginUrl'];
    parse_str(parse_url($replacementUrl, PHP_URL_QUERY), $replacementQuery);

    expect(MagicLoginToken::query()->count())->toBe(2)
        ->and($first->fresh()->expires_at->equalTo(now()->subSeconds(30)))->toBeTrue()
        ->and($replacement->token_hash)->toBe(hash('sha256', $replacementQuery['token']))->not->toBe($first->token_hash)
        ->and($replacement->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue()
        ->and($replacement->used_at)->toBeNull()
        ->and($replacement->user_agent)->toBe($expectedAgent)
        ->and(MagicLoginToken::query()->get()->toJson())->not->toContain($firstQuery['token'], $replacementQuery['token']);
    Notification::assertSentToTimes($subject, MagicLoginLinkNotification::class, 2);
})->with([
    'auth:invite' => ['console', null, 'cli'],
    'reinvite with absent agent' => ['reinvite', null, 'admin-user-reinvite'],
    'reinvite with blank agent' => ['reinvite', '', 'admin-user-reinvite'],
    'reinvite with supplied agent' => ['reinvite', 'admin-browser', 'admin-browser'],
]);

it('creates and whitelists a new account through auth invite with a matching token', function (): void {
    expect(Artisan::call('auth:invite', ['email' => '  NEW@Example.COM ']))->toBe(0);
    $subject = User::query()->sole();
    $url = Notification::sent($subject, MagicLoginLinkNotification::class)->sole()->toMail($subject)->viewData['loginUrl'];
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $token = MagicLoginToken::query()->sole();

    expect($subject->email)->toBe('new@example.com')
        ->and($subject->whitelisted_at)->not->toBeNull()
        ->and($token->email)->toBe($subject->email)
        ->and($token->token_hash)->toBe(hash('sha256', $query['token']))
        ->and($token->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue()
        ->and($token->ip)->toBe('cli')
        ->and($token->user_agent)->toBe('cli');
});
