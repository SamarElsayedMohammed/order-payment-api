<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Support\ErrorLogger;

class ErrorResponse extends BaseApiResponse
{
    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $message = null,
        array $errors = [],
        mixed $data = null,
        ?int $httpStatus = null,
        public readonly array $context = [],
        bool $log = false,
    ) {
        $resolvedMessage = $message ?? $errorCode->message();

        if ($log) {
            ErrorLogger::logBusiness($errorCode, $resolvedMessage, $context);
        }

        parent::__construct(
            data: $data,
            message: $resolvedMessage,
            code: $errorCode->value,
            httpStatus: $httpStatus ?? $errorCode->httpStatus(),
            errors: $errors,
        );
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $context
     */
    public static function logged(
        ErrorCode $errorCode,
        ?string $message = null,
        array $errors = [],
        mixed $data = null,
        ?int $httpStatus = null,
        array $context = [],
    ): self {
        return new self(
            errorCode: $errorCode,
            message: $message,
            errors: $errors,
            data: $data,
            httpStatus: $httpStatus,
            context: $context,
            log: true,
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
