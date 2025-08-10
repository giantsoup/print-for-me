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

        // Rate limit magic link requests to 5/hour per email+IP
        RateLimiter::for('magic-link', function (Request $request) {
            $email = (string) $request->input('email');
            $ip = (string) $request->ip();

            return [
                Limit::perHour(5)->by($ip.'|'.$email),
            ];
        });
    }
}
