<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// Public (guest) routes
Route::middleware('guest')->group(function () {
    // Show login page (magic link UI)
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
});

// Magic link auth flow (guest)
Route::middleware('guest')->group(function () {
    Route::get('magic-login/request', [MagicLinkController::class, 'create'])
        ->name('magic.request');

    Route::post('magic-login/send', [MagicLinkController::class, 'store'])
        ->middleware('throttle:magic.send')
        ->name('magic.send');

    Route::get('magic-login', [MagicLinkController::class, 'login'])
        ->middleware(['throttle:6,1'])
        ->name('magic.login');

    Route::get('magic-login/result', [MagicLinkController::class, 'result'])
        ->name('magic.result');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Email verification
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Logout
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
