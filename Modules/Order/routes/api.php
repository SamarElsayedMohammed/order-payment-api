<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;

Route::prefix('v1')->middleware('auth:api')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])->whereNumber('order');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->whereNumber('order');
});
