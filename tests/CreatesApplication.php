<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        Http::preventStrayRequests();
        $app['config']->set('inertia.ssr.enabled', false);

        return $app;
    }
}
