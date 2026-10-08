<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Internal tool: every page needs a login (HTTP basic auth against the users table).
Route::middleware('auth.basic')->group(function () {
    Route::redirect('/', '/imports');

    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{import}/apply', [ImportController::class, 'apply'])->name('imports.apply');
    Route::post('/imports/{import}/rollback', [ImportController::class, 'rollback'])->name('imports.rollback');
    Route::post('/imports/{import}/discard', [ImportController::class, 'discard'])->name('imports.discard');
    Route::get('/imports/{import}/errors.csv', [ImportController::class, 'errors'])->name('imports.errors');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
});
