<?php

use App\Models\User;
use Illuminate\Http\Request;

it('forces re-login when last_login_at exceeds session lifetime', function () {
    // Create a user whose last login is well beyond the allowed lifetime
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->forceFill(['last_login_at' => now()->subMinutes(120)])->save();

    // Invoke the middleware directly with a forced header in testing
    $middleware = new \App\Http\Middleware\EnforceAbsoluteSession();

    $server = ['HTTP_X-Force-Absolute' => '1'];
    $request = Request::create('/abs-direct', 'GET', server: $server);
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    $response = $middleware->handle($request, fn () => response('OK'));

    expect($response->isRedirect(route('magic.request')))->toBeTrue();
});
