<?php

declare(strict_types=1);

namespace Modules\Payment\Gateways;

use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Order\Models\Order;
use Modules\Payment\DTOs\GatewayResponseData;

class PayPalGateway extends AbstractPaymentGateway
{
    private const TOKEN_CACHE_KEY = 'payment:paypal:access_token';

    public function identifier(): string
    {
        return 'paypal';
    }

    protected function requiredConfigKeys(): array
    {
        return ['client_id', 'client_secret'];
    }

    protected function buildChargeRequest(Order $order, array $payload): array
    {
        return array_merge(parent::buildChargeRequest($order, $payload), [
            'intent' => 'CAPTURE',
            'payer_email' => $payload['payer_email'] ?? $order->customer_email,
            'purchase_units' => [
                [
                    'reference_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $this->config['currency'] ?? 'USD',
                        'value' => (string) $order->total,
                    ],
                ],
            ],
        ]);
    }

    protected function executeCharge(Order $order, array $preparedPayload, array $originalPayload): GatewayResponseData|ErrorResponse
    {
        if ($this->isSimulation()) {
            $declined = ($preparedPayload['simulate_outcome'] ?? 'success') === 'failed';

            return new GatewayResponseData(
                $declined ? 422 : 201,
                ! $declined,
                $declined
                    ? ['id' => $this->generateTransactionReference(), 'reason' => 'INSTRUMENT_DECLINED']
                    : ['id' => $this->generateTransactionReference(), 'status' => 'COMPLETED'],
            );
        }

        $response = $this->charge($preparedPayload, $originalPayload);

        if ($response instanceof ErrorResponse) {
            return $response;
        }

        if ($response->httpStatus === 401) {
            $this->http->forgetClientCredentialsToken(self::TOKEN_CACHE_KEY, $this->baseUrl(), $this->clientId());
            $response = $this->charge($preparedPayload, $originalPayload);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $preparedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    private function charge(array $preparedPayload, array $originalPayload): GatewayResponseData|ErrorResponse
    {
        $token = $this->http->clientCredentialsToken(
            cacheKey: self::TOKEN_CACHE_KEY,
            baseUrl: $this->baseUrl(),
            tokenEndpoint: '/v1/oauth2/token',
            clientId: $this->clientId(),
            clientSecret: (string) ($this->config['client_secret'] ?? ''),
            timeout: (int) ($this->config['timeout'] ?? 15),
            gateway: $this->identifier(),
        );

        if ($token instanceof ErrorResponse) {
            return $token;
        }

        return $this->http->send(
            method: 'POST',
            baseUrl: $this->baseUrl(),
            endpoint: '/v2/checkout/orders',
            params: $preparedPayload,
            headers: [
                'Authorization' => 'Bearer '.$token,
            ],
            idempotencyKey: $originalPayload['idempotency_key'] ?? null,
            timeout: (int) ($this->config['timeout'] ?? 15),
            gateway: $this->identifier(),
        );
    }

    private function clientId(): string
    {
        return (string) ($this->config['client_id'] ?? '');
    }
}
