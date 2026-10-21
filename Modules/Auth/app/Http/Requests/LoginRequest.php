<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Requests;

use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::LoginValidationFailed;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
