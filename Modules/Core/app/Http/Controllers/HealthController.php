<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;

class HealthController extends BaseController
{
    /**
     * Report that the API is reachable. Does not require authentication.
     */
    public function __invoke(): SuccessResponse
    {
        return new SuccessResponse(
            data: ['status' => 'ok'],
            message: 'The API is running.',
            code: SuccessCode::HEALTH,
        );
    }
}
