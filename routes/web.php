<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $user = request()->user();

    // Redirect only if the user is authenticated AND not an admin
    if ($user && ! (bool) ($user->is_admin ?? false)) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Home');
})->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'absolute', 'session_version', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/prints.php';
require __DIR__.'/admin.php';
