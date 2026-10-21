<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Exception;
use Modules\Core\Enums\ErrorCode;
use Throwable;

class ApiBusinessException extends Exception
{
    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $message = null,
        public readonly array $errors = [],
        public readonly mixed $data = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? $errorCode->message(), $errorCode->httpStatus(), $previous);
    }
}
