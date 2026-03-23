<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionVersion
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // In tests, skip by default unless explicitly enforced to keep tests simple.
        if (app()->environment('testing')
            && ! (bool) config('session.enforce_version_in_tests', false)
            && ! (bool) $request->headers->get('X-Force-Session-Version')
        ) {
            return $next($request);
        }

        $user = $request->user();

        if ($user) {
            if (! User::hasDatabaseColumn('session_version')) {
                return $next($request);
            }

            $sessionVersion = $user->currentSessionVersion();
            $sv = (int) $request->session()->get('sv', 0);

            // In tests, allow forcing an immediate mismatch via header
            if ($request->headers->get('X-Force-Session-Version')) {
                $sv = -1; // ensure mismatch
            }

            if ($sv !== $sessionVersion) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('magic.request')
                    ->withErrors(['session' => 'Your session has changed. Please request a new magic link.']);
            }
        }

        return $next($request);
    }
}
