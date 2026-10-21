<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;
use Modules\Core\Support\ErrorLogger;
use Modules\Payment\DTOs\GatewayResponseData;
use Throwable;

class GatewayHttpClient
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public function send(
        string $method,
        string $baseUrl,
        string $endpoint,
        array $params = [],
        array $headers = [],
        string $bodyFormat = 'json',
        ?string $idempotencyKey = null,
        int $timeout = 15,
        string $gateway = 'gateway',
    ): GatewayResponseData {
        $method = strtoupper($method);
        $headers = array_merge([
            'Accept' => 'application/json',
        ], $headers);

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $options = $this->options($method, $params, $bodyFormat);

        try {
            $response = Http::baseUrl(rtrim($baseUrl, '/'))
                ->withHeaders($headers)
                ->timeout($timeout)
                ->retry(2, 200, function (Throwable $exception, mixed $request, ?string $method = null): bool {
                    return $this->shouldRetry($method ?? 'POST', $exception);
                }, throw: false)
                ->send($method, '/'.ltrim($endpoint, '/'), $options);
        } catch (Throwable $exception) {
            ErrorLogger::logGateway($gateway, $method, $endpoint, $params, 0, [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $body = $response->json();
        $decoded = is_array($body) ? $body : ['raw' => $response->body()];

        ErrorLogger::logGateway($gateway, $method, $endpoint, $params, $response->status(), $decoded);

        return new GatewayResponseData(
            httpStatus: $response->status(),
            successful: $response->successful(),
            body: $decoded,
        );
    }

    /**
     * Fetch and cache an OAuth client-credentials token. The token request is a POST, so it is not retried.
     */
    public function clientCredentialsToken(
        string $cacheKey,
        string $baseUrl,
        string $tokenEndpoint,
        string $clientId,
        string $clientSecret,
        int $ttlSeconds = 3300,
        int $timeout = 15,
        string $gateway = 'gateway',
    ): string {
        $cacheKey .= ':'.hash('sha256', $baseUrl.'|'.$clientId);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->send(
                method: 'POST',
                baseUrl: $baseUrl,
                endpoint: $tokenEndpoint,
                params: ['grant_type' => 'client_credentials'],
                headers: [
                    'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
                ],
                bodyFormat: 'form_params',
                timeout: $timeout,
                gateway: $gateway,
            );

        $accessToken = $response->body['access_token'] ?? null;

        if (! $response->successful || ! is_string($accessToken) || $accessToken === '') {
            throw new ApiBusinessException(ErrorCode::PaymentGatewayFailed, 'Unable to authenticate with the payment gateway.');
        }

        $expiresIn = (int) ($response->body['expires_in'] ?? $ttlSeconds);
        Cache::put($cacheKey, $accessToken, max(60, min($ttlSeconds, $expiresIn - 60)));

        return $accessToken;
    }

    public function forgetClientCredentialsToken(string $cacheKey, string $baseUrl, string $clientId): void
    {
        Cache::forget($cacheKey.':'.hash('sha256', $baseUrl.'|'.$clientId));
    }

    public function shouldRetry(string $method, Throwable $exception): bool
    {
        return strtoupper($method) === 'GET' && $exception instanceof ConnectionException;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function options(string $method, array $params, string $bodyFormat): array
    {
        if ($method === 'GET') {
            return ['query' => $params];
        }

        if ($bodyFormat === 'form_params') {
            return ['form_params' => $params];
        }

        return ['json' => $params];
    }
}
