<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ErrorCodeIndexController;
use Modules\Core\Http\Middleware\DeveloperToolsAuthMiddleware;

Route::get('/docs/error-codes', ErrorCodeIndexController::class)
    ->middleware(DeveloperToolsAuthMiddleware::class);
