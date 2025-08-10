<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Random\RandomException;

class MagicLinkController extends Controller
{
    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('auth/RequestMagicLink', [
            'status' => session('status'),
            'errors' => session('errors') ? session('errors')->getBag('default')->getMessages() : [],
        ]);
    }

    /**
     * @throws RandomException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns'],
        ]);

        $email = strtolower($validated['email']);

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->whitelisted_at) {
            // Intentionally vague to avoid user enumeration
            throw ValidationException::withMessages([
                'email' => 'This email is not authorized. You must be invited first.',
            ]);
        }

        // Generate token
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        MagicLoginToken::create([
            'email' => $email,
            'token_hash' => $hash,
            'expires_at' => now()->addMinutes(10),
            'ip' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        // Build signed URL with parameters
        $loginUrl = URL::temporarySignedRoute(
            'magic.login',
            now()->addMinutes(10),
            ['email' => $email, 'token' => $raw]
        );

        // Notify user via email (logged locally)
        $user->notify(new MagicLoginLinkNotification($loginUrl));

        return back()->with('status', 'Magic link sent! It expires in 10 minutes.');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string', 'size:64'],
        ]);

        $email = strtolower($request->string('email'));
        $rawToken = $request->string('token');
        $hash = hash('sha256', $rawToken);

        $token = MagicLoginToken::where('email', $email)
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            throw ValidationException::withMessages([
                'token' => 'This magic link is invalid or has expired.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'This account does not exist.',
            ]);
        }

        // Mark token used
        $token->forceFill(['used_at' => now()])->save();

        // Login user and update timestamps
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
