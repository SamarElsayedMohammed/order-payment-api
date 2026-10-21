<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Responses;

use Modules\Auth\Http\Resources\UserResource;
use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;

class UserProfileResponse extends SuccessResponse
{
    public function __construct(UserResource $user)
    {
        parent::__construct(
            data: $user,
            message: 'Authenticated user retrieved successfully.',
            code: SuccessCode::PROFILE,
        );
    }
}
