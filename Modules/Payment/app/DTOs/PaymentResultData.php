<?php

declare(strict_types=1);

namespace Modules\Payment\DTOs;

final class PaymentResultData
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $transactionId,
        public readonly string $gateway,
        public readonly array $rawResponse = [],
        public readonly ?string $failureReason = null,
    ) {}
}
