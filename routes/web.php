<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    $user = request()->user();

    $query = \App\Models\PrintRequest::query()->latest()->limit(5);
    if (!($user->is_admin ?? false)) {
        $query->where('user_id', $user->id);
    }

    $recent = $query->get(['id', 'status', 'created_at']);

    return Inertia::render('Dashboard', [
        'recentRequests' => $recent,
        'isAdmin' => (bool) ($user->is_admin ?? false),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/prints.php';
require __DIR__.'/admin.php';
