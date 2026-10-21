<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Responses;

use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseApiResponse;

class AuthTokenResponse extends BaseApiResponse
{
    public function __construct(
        private readonly string $token,
        string $message,
        string $code,
        int $httpStatus = 200,
    ) {
        parent::__construct(data: null, message: $message, code: $code, httpStatus: $httpStatus);
    }

    protected function formatData(Request $request): mixed
    {
        return [
            'token' => $this->token,
            'token_type' => 'bearer',
            'expires_in' => (int) auth('api')->factory()->getTTL() * 60,
        ];
    }
}
