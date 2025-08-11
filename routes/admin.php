<?php

use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\PrintRequestStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'absolute', 'admin'])->group(function () {
    // Invite a user by email (creates/updates and sends magic link)
    Route::get('admin/invite', [InviteController::class, 'create'])->name('admin.invite.create');
    Route::post('admin/invite', [InviteController::class, 'store'])->name('admin.invite.store');

    // Print Request status transitions
    Route::patch('admin/print-requests/{print_request}/accept', [PrintRequestStatusController::class, 'accept'])
        ->name('admin.print-requests.accept');
    Route::patch('admin/print-requests/{print_request}/printing', [PrintRequestStatusController::class, 'printing'])
        ->name('admin.print-requests.printing');
    Route::patch('admin/print-requests/{print_request}/complete', [PrintRequestStatusController::class, 'complete'])
        ->name('admin.print-requests.complete');
    Route::patch('admin/print-requests/{print_request}/revert', [PrintRequestStatusController::class, 'revert'])
        ->name('admin.print-requests.revert');
});
