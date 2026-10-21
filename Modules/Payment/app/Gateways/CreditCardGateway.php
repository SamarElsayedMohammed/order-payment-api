<?php

declare(strict_types=1);

namespace Modules\Payment\Gateways;

use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Order\Models\Order;
use Modules\Payment\DTOs\GatewayResponseData;

class CreditCardGateway extends AbstractPaymentGateway
{
    public function identifier(): string
    {
        return 'credit_card';
    }

    protected function requiredConfigKeys(): array
    {
        return ['api_key', 'secret_key'];
    }

    protected function validatePayload(array $payload): ?ErrorResponse
    {
        if ($this->isSimulation()) {
            return null;
        }

        foreach (['card_number', 'cvv'] as $field) {
            if (empty($payload[$field])) {
                return ErrorResponse::logged(
                    ErrorCode::PaymentValidationFailed,
                    'The '.$field.' field is required for the credit card gateway.',
                );
            }
        }

        return null;
    }

    protected function buildChargeRequest(Order $order, array $payload): array
    {
        return array_merge(parent::buildChargeRequest($order, $payload), [
            'card_number' => $payload['card_number'] ?? null,
            'cvv' => $payload['cvv'] ?? null,
        ]);
    }

    protected function executeCharge(Order $order, array $preparedPayload, array $originalPayload): GatewayResponseData
    {
        if ($this->isSimulation()) {
            return $this->simulatedResponse($preparedPayload);
        }

        return $this->http->send(
            method: 'POST',
            baseUrl: $this->baseUrl(),
            endpoint: '/v1/charges',
            params: $preparedPayload,
            headers: [
                'Authorization' => 'Bearer '.($this->config['secret_key'] ?? ''),
                'X-Api-Key' => (string) ($this->config['api_key'] ?? ''),
            ],
            idempotencyKey: $originalPayload['idempotency_key'] ?? null,
            timeout: (int) ($this->config['timeout'] ?? 15),
            gateway: $this->identifier(),
        );
    }

    /**
     * @param  array<string, mixed>  $preparedPayload
     */
    private function simulatedResponse(array $preparedPayload): GatewayResponseData
    {
        $declined = ($preparedPayload['simulate_outcome'] ?? 'success') === 'failed'
            || str_ends_with((string) ($preparedPayload['card_number'] ?? ''), '0000');

        if ($declined) {
            return new GatewayResponseData(402, false, [
                'id' => $this->generateTransactionReference(),
                'reason' => 'Card declined by issuer',
            ]);
        }

        return new GatewayResponseData(200, true, [
            'id' => $this->generateTransactionReference(),
            'auth_code' => 'AUTH'.random_int(100000, 999999),
        ]);
    }
}
