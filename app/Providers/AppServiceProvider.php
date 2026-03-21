<?php

namespace App\Providers;

use App\Models\PrintRequest;
use App\Policies\PrintRequestPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register policy mapping
        Gate::policy(PrintRequest::class, PrintRequestPolicy::class);

        // Friendly, invite-only magic link limiter used by the send endpoint.
        RateLimiter::for('magic.send', function (Request $request) {
            $email = (string) $request->input('email');
            $ip = (string) $request->ip();
            $key = $email.'|'.$ip;

            return Limit::perHour(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return back()
                        ->withErrors(['session' => 'Too many requests. Please wait a bit before trying again.'])
                        ->setStatusCode(429)
                        ->withHeaders($headers);
                });
        });
    }
}
