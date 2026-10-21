<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Order\Models\Order;
use Modules\Payment\DTOs\PaymentResultData;

interface PaymentGatewayInterface
{
    public function identifier(): string;

    public function displayName(): string;

    public function isEnabled(): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(Order $order, array $payload = []): PaymentResultData|ErrorResponse;
}
