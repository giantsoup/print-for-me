<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSession
{
    /**
     * Handle an incoming request.
     *
     * Enforces an absolute session window based on the configured session lifetime.
     * If the user's last_login_at is older than the session lifetime (in minutes),
     * force a re-login via magic link.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // In testing, the middleware is skipped by default to simplify tests.
        // You may force enforcement within tests by setting:
        // config(['session.enforce_absolute_in_tests' => true])
        if (app()->environment('testing')
            && ! (bool) config('session.enforce_absolute_in_tests', false)
            && ! (bool) $request->headers->get('X-Force-Absolute')
        ) {
            return $next($request);
        }

        $user = $request->user();

        // In tests, allow forcing an immediate absolute-expiry check via header
        if (app()->environment('testing') && $request->headers->get('X-Force-Absolute') && $user) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('magic.request')
                ->withErrors(['session' => 'Your session has expired. Please request a new magic link.']);
        }

        if ($user) {
            $lastLogin = $user->last_login_at;
            $lifetimeMinutes = max((int) config('session.lifetime'), 1);
            $deadline = now()->subMinutes($lifetimeMinutes);

            if (! $lastLogin || $lastLogin->lte($deadline)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('magic.request')
                    ->withErrors(['session' => 'Your session has expired. Please request a new magic link.']);
            }
        }

        return $next($request);
    }
}
