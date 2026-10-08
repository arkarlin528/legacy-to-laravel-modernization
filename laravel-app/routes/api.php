<?php

use App\Http\Controllers\LegacyV1;
use App\Http\Controllers\V2;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| v2: the new API
|--------------------------------------------------------------------------
| snake_case JSON, UTC ISO-8601 timestamps, pagination, per-user Sanctum tokens.
*/
Route::prefix('v2')->name('v2.')->group(function () {
    Route::post('tokens', [V2\TokenController::class, 'store'])->middleware('throttle:10,1')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::get('customers', [V2\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [V2\CustomerController::class, 'show'])->name('customers.show');
        Route::get('orders', [V2\OrderController::class, 'index'])->name('orders.index');
        Route::post('orders', [V2\OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [V2\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/cancel', [V2\OrderController::class, 'cancel'])->name('orders.cancel');
    });
});

/*
|--------------------------------------------------------------------------
| v1: legacy-compatible API
|--------------------------------------------------------------------------
| Same URLs, JSON and errors as the old ASP.NET service, so the strangler facade can route any of
| these paths to either system without clients noticing. Verified by ../contract-tests.
*/
Route::middleware('legacy.key')->name('v1.')->group(function () {
    Route::get('customers', [LegacyV1\CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [LegacyV1\CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{id}', [LegacyV1\CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
    Route::get('customers/{id}/invoices', [LegacyV1\CustomerController::class, 'invoices'])->whereNumber('id')->name('customers.invoices');

    Route::get('orders', [LegacyV1\OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [LegacyV1\OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{id}', [LegacyV1\OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
    Route::post('orders/{id}/cancel', [LegacyV1\OrderController::class, 'cancel'])->whereNumber('id')->name('orders.cancel');
});
