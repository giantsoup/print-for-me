<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        \Illuminate\Support\Facades\Gate::policy(\App\Models\PrintRequest::class, \App\Policies\PrintRequestPolicy::class);

        // Rate limit magic link requests.
        // We combine multiple limits; Laravel will enforce all of them.
        // - Per email+IP: 5/hour (existing behavior) to deter repeated requests for a specific identity.
        // - Per IP: 10/minute to slow simple floods from a single source.
        RateLimiter::for('magic-link', function (Request $request) {
            $email = (string) $request->input('email');
            $ip = (string) $request->ip();

            return [
                // Conservative hourly limit tied to both IP and email.
                Limit::perHour(5)->by($ip.'|'.$email),
                // Short-burst limiter per IP to absorb spikes without impacting normal use.
                Limit::perMinute(10)->by($ip),
            ];
        });
    }
}
