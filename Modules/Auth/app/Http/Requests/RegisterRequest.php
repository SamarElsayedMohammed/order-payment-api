<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Requests;

use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Requests\BaseFormRequest;

class RegisterRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::RegistrationValidationFailed;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
