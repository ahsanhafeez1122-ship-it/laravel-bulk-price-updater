<?php

use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::get('/imports/{import}/rows', [ImportController::class, 'rows'])->name('imports.rows');
    Route::post('/imports/{import}/apply', [ImportController::class, 'apply'])->name('imports.apply');
    Route::post('/imports/{import}/rollback', [ImportController::class, 'rollback'])->name('imports.rollback');
});
