<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Enums\ErrorCode;

class ErrorResponse extends BaseApiResponse
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        ErrorCode $errorCode,
        ?string $message = null,
        array $errors = [],
        mixed $data = null,
        ?int $httpStatus = null,
    ) {
        parent::__construct(
            data: $data,
            message: $message ?? $errorCode->message(),
            code: $errorCode->value,
            httpStatus: $httpStatus ?? $errorCode->httpStatus(),
            errors: $errors,
        );
    }

    protected function formatData(Request $request): mixed
    {
        if ($this->data instanceof JsonResource) {
            return $this->data->resolve($request);
        }

        return $this->data;
    }
}
