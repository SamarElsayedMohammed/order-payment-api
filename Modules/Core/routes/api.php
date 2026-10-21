<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\HealthController;

Route::get('v1/health', HealthController::class);
