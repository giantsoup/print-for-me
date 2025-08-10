<?php

use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:invite {email}', function (string $email) {
    $email = strtolower($email);

    $user = User::firstOrNew(['email' => $email]);
    if (! $user->exists) {
        $user->name = strstr($email, '@', true) ?: $email;
        $user->password = Str::random(40); // Will be hashed by cast
    }
    $user->whitelisted_at = now();
    $user->save();

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    MagicLoginToken::create([
        'email' => $email,
        'token_hash' => $hash,
        'expires_at' => now()->addMinutes(10),
        'ip' => 'cli',
        'user_agent' => 'cli',
    ]);

    $loginUrl = URL::temporarySignedRoute('magic.login', now()->addMinutes(10), [
        'email' => $email,
        'token' => $raw,
    ]);

    $user->notify(new MagicLoginLinkNotification($loginUrl));

    $this->info("Invited: {$email}");
    $this->line('Magic login link (10 minutes):');
    $this->line($loginUrl);
})->purpose('Invite a user by email, whitelist them, and send a magic login link.');
