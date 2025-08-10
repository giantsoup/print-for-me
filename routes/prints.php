<?php

use App\Http\Controllers\PrintRequestController;
use App\Http\Controllers\PrintRequestFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Print Request CRUD
    Route::get('print-requests', [PrintRequestController::class, 'index'])->name('print-requests.index');
    Route::post('print-requests', [PrintRequestController::class, 'store'])->name('print-requests.store');
    Route::get('print-requests/{print_request}', [PrintRequestController::class, 'show'])->middleware('can:view,print_request')->name('print-requests.show');
    Route::patch('print-requests/{print_request}', [PrintRequestController::class, 'update'])->middleware('can:update,print_request')->name('print-requests.update');
    Route::delete('print-requests/{print_request}', [PrintRequestController::class, 'destroy'])->middleware('can:delete,print_request')->name('print-requests.destroy');

    // Permanent delete for owner's soft-deleted pending request
    Route::delete('print-requests/{id}/force', [PrintRequestController::class, 'forceDestroy'])->name('print-requests.force-destroy');

    // Secure file download
    Route::get('print-requests/{print_request}/files/{file}/download', [PrintRequestFileController::class, 'download'])
        ->middleware('can:download,print_request')->name('print-requests.files.download');
});
