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
        $user = $request->user();

        if ($user) {
            $lastLogin = $user->last_login_at;
            $lifetimeMinutes = (int) config('session.lifetime');

            if (! $lastLogin || now()->diffInMinutes($lastLogin) > $lifetimeMinutes) {
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
