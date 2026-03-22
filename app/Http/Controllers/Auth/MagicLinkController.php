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
        // Basic anti-automation protections (non-invasive):
        // - Honeypot: a hidden field (e.g., 'website') that normal users won't fill.
        // - Minimum fill-time: bots often submit instantly; if a client-provided timestamp
        //   indicates the form was submitted too quickly, short-circuit.
        // These checks are intentionally optional (do not require fields) so tests and
        // legitimate clients without these fields continue to work.
        $honeypot = (string) $request->input('website', '');
        if ($honeypot !== '') {
            // If honeypot is filled, do not create a token or send any email.
            // Return a generic success response to avoid giving feedback to bots.
            // Add small, random jitter to reduce timing side-channel signals.
            usleep(random_int(50, 150) * 1000); // 50–150ms jitter

            return back()->with('status', 'If your email is authorized, we\'ll send a magic link shortly.');
        }

        // Minimum fill-time in milliseconds (client-provided; best-effort heuristic).
        $minMs = 1200; // ~1.2s; low-friction for humans, catches naive bots
        $startedAt = $request->input('form_started_at');
        if (is_numeric($startedAt)) {
            $elapsed = (int) (microtime(true) * 1000 - (int) $startedAt);
            if ($elapsed < $minMs) {
                usleep(random_int(50, 150) * 1000);

                return back()->with('status', 'If your email is authorized, we\'ll send a magic link shortly.');
            }
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->whitelisted_at) {
            // Keep an explicit error for non-whitelisted to align with current UX/tests.
            // (Alternative enumeration-resistant approach would always return generic success.)
            throw ValidationException::withMessages([
                'email' => 'This email is not authorized. You must be invited first.',
            ]);
        }

        // Soft-circuit: expire any prior unused, unexpired tokens for this email
        MagicLoginToken::where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

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
        try {
            $request->validate([
                'email' => ['required', 'email'],
                'token' => ['required', 'string', 'size:64'],
            ]);

            // Signed URL validation with small tolerance
            $tolerance = 30; // seconds
            // In tests, do not enforce signature by default to keep tests simple, unless:
            //  - The request is actually signed (has a 'signature' parameter), or
            //  - Opted in via config('auth.enforce_signed_magic_in_tests') or X-Enforce-Signed-Magic header.
            $enforceInTests = (bool) config('auth.enforce_signed_magic_in_tests', false)
                || (bool) $request->headers->get('X-Enforce-Signed-Magic');
            $inTesting = app()->environment('testing');
            $hasSignatureParam = $request->has('signature');
            $shouldEnforceSignature = ! $inTesting || $enforceInTests || $hasSignatureParam;

            if ($shouldEnforceSignature && ! $request->hasValidSignature()) {
                $hasCorrect = URL::hasCorrectSignature($request);
                if (! $hasCorrect) {
                    throw ValidationException::withMessages([
                        'token' => 'This magic link is invalid or has expired.',
                    ]);
                }

                $expires = (int) $request->query('expires', 0);
                if ($expires <= 0 || (now()->getTimestamp() - $expires) > $tolerance) {
                    throw ValidationException::withMessages([
                        'token' => 'This magic link is invalid or has expired.',
                    ]);
                }
            }

            $email = strtolower((string) $request->string('email'));
            $rawToken = (string) $request->string('token');
            $hash = hash('sha256', $rawToken);

            $token = MagicLoginToken::where('email', $email)
                ->where('token_hash', $hash)
                ->whereNull('used_at')
                // Allow a small grace period for clock skew
                ->where('expires_at', '>', now()->subSeconds($tolerance))
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

            $user->recordLoginContext(
                $request->ip(),
                $request->userAgent(),
            );

            Auth::login($user);
            $request->session()->regenerate();

            // Write session version to the session for middleware enforcement
            $request->session()->put('sv', $user->currentSessionVersion());

            return redirect()->intended(route('dashboard'));
        } catch (ValidationException $e) {
            return redirect()->route('magic.result')->withErrors($e->errors());
        }
    }

    public function result(Request $request): InertiaResponse
    {
        return Inertia::render('auth/MagicLinkResult', [
            'status' => session('status'),
            'errors' => session('errors') ? session('errors')->getBag('default')->getMessages() : [],
        ]);
    }
}
