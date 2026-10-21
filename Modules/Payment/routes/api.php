<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;

Route::prefix('v1')->middleware('auth:api')->group(function (): void {
    Route::get('payment-methods', [PaymentController::class, 'methods']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment');
    Route::get('orders/{order}/payments', [PaymentController::class, 'forOrder'])->whereNumber('order');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->whereNumber('order');
});
