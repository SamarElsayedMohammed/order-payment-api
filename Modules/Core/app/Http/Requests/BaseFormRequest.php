<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;

abstract class BaseFormRequest extends FormRequest
{
    abstract protected function errorCode(): ErrorCode;

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ErrorResponse(
                errorCode: $this->errorCode(),
                errors: $validator->errors()->toArray(),
            ))->toResponse($this)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            (new ErrorResponse(errorCode: ErrorCode::Forbidden))->toResponse($this)
        );
    }

    /**
     * @param  list<string>  $fields
     */
    protected function stripClientFields(array $fields): void
    {
        $payload = $this->all();

        foreach ($fields as $field) {
            unset($payload[$field]);
        }

        $this->replace($payload);
    }
}
