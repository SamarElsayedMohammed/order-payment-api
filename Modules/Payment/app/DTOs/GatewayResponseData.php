<?php

declare(strict_types=1);

namespace Modules\Payment\DTOs;

final class GatewayResponseData
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly int $httpStatus,
        public readonly bool $successful,
        public readonly array $body = [],
    ) {}
}
