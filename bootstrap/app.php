<?php

use App\Http\Middleware\EnforceAbsoluteSession;
use App\Http\Middleware\EnforceSessionVersion;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Route middleware aliases
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'absolute' => EnforceAbsoluteSession::class,
            'session_version' => EnforceSessionVersion::class,
        ]);

        // Register rate limiters specific to this application.
        RateLimiter::for('magic.send', function (Request $request) {
            $email = (string) $request->input('email');
            $ip = (string) $request->ip();
            $key = $email.'|'.$ip;

            // Provide a friendly redirect response on limit to avoid enumeration and preserve UX.
            return Limit::perHour(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return back()
                        ->withErrors(['session' => 'Too many requests. Please wait a bit before trying again.'])
                        ->setStatusCode(429)
                        ->withHeaders($headers);
                });
        });

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
