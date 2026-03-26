<?php

use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\PrintRequestSourcePreviewController;
use App\Http\Controllers\Admin\PrintRequestStatusController;
use App\Http\Controllers\Admin\SourcePreviewDomainController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', 'absolute', 'session_version', 'admin']], function () {
    // Invite a user by email (creates/updates and sends magic link)
    Route::get('admin/invite', [InviteController::class, 'create'])->name('admin.invite.create');
    Route::post('admin/invite', [InviteController::class, 'store'])->name('admin.invite.store');

    Route::get('admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('admin/users/{user}', [UserController::class, 'show'])->withTrashed()->name('admin.users.show');
    Route::patch('admin/users/{user}', [UserController::class, 'update'])->withTrashed()->name('admin.users.update');
    Route::post('admin/users/{user}/invite', [UserController::class, 'invite'])->withTrashed()->name('admin.users.invite');
    Route::post('admin/users/{user}/access/revoke', [UserController::class, 'revokeAccess'])->withTrashed()->name('admin.users.access.revoke');
    Route::post('admin/users/{user}/access/restore', [UserController::class, 'restoreAccess'])->withTrashed()->name('admin.users.access.restore');
    Route::post('admin/users/{user}/sessions/invalidate', [UserController::class, 'invalidateSessions'])
        ->withTrashed()->name('admin.users.sessions.invalidate');
    Route::post('admin/users/{user}/role/promote', [UserController::class, 'promote'])->withTrashed()->name('admin.users.role.promote');
    Route::post('admin/users/{user}/role/demote', [UserController::class, 'demote'])->withTrashed()->name('admin.users.role.demote');
    Route::delete('admin/users/{user}', [UserController::class, 'destroy'])->withTrashed()->name('admin.users.destroy');
    Route::post('admin/users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('admin.users.restore');
    Route::delete('admin/users/{user}/purge', [UserController::class, 'purge'])->withTrashed()->name('admin.users.purge');

    // Print Request status transitions
    Route::patch('admin/print-requests/{print_request}/accept', [PrintRequestStatusController::class, 'accept'])
        ->name('admin.print-requests.accept');
    Route::patch('admin/print-requests/{print_request}/printing', [PrintRequestStatusController::class, 'printing'])
        ->name('admin.print-requests.printing');
    Route::patch('admin/print-requests/{print_request}/complete', [PrintRequestStatusController::class, 'complete'])
        ->name('admin.print-requests.complete');
    Route::patch('admin/print-requests/{print_request}/revert', [PrintRequestStatusController::class, 'revert'])
        ->name('admin.print-requests.revert');
    Route::post('admin/print-requests/{print_request}/notifications/completed/resend', [PrintRequestStatusController::class, 'resendCompletedNotification'])
        ->name('admin.print-requests.notifications.completed.resend');
    Route::post('admin/print-requests/{print_request}/source-preview/refetch', PrintRequestSourcePreviewController::class)
        ->name('admin.print-requests.source-preview.refetch');

    Route::get('admin/source-preview-domains', [SourcePreviewDomainController::class, 'index'])
        ->name('admin.source-preview-domains.index');
    Route::patch('admin/source-preview-domains/{source_preview_domain}', [SourcePreviewDomainController::class, 'update'])
        ->name('admin.source-preview-domains.update');
    Route::post('admin/source-preview-domains/{source_preview_domain}/attempt', [SourcePreviewDomainController::class, 'attempt'])
        ->name('admin.source-preview-domains.attempt');
    Route::post('admin/source-preview-domains/{source_preview_domain}/attempt-url', [SourcePreviewDomainController::class, 'attemptUrl'])
        ->name('admin.source-preview-domains.attempt-url');
});
