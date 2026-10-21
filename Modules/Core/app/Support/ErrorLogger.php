<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Log;
use Modules\Core\Enums\ErrorCode;
use Throwable;

final class ErrorLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function logException(Throwable $exception, ErrorCode $errorCode, array $context = []): void
    {
        $payload = self::context($errorCode, $context);
        $payload['exception'] = $exception;

        Log::channel('daily')->error($exception->getMessage(), $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function logBusiness(ErrorCode $errorCode, string $message, array $context = []): void
    {
        Log::channel('daily')->warning($message, self::context($errorCode, $context));
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    public static function logGateway(string $gateway, string $method, string $endpoint, array $request, int $status, array $response): void
    {
        $masker = new SensitiveDataMasker;

        Log::channel('payments')->info('Payment gateway call', [
            'user_id' => self::userId(),
            'gateway' => $gateway,
            'method' => $method,
            'endpoint' => $endpoint,
            'request' => $masker->mask($request),
            'response_status' => $status,
            'response' => $masker->mask($response),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function context(ErrorCode $errorCode, array $context): array
    {
        return array_merge((new SensitiveDataMasker)->mask($context), [
            'error_code' => $errorCode->value,
            'user_id' => self::userId(),
        ]);
    }

    private static function userId(): int|string|null
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        try {
            return auth('api')->id();
        } catch (Throwable) {
            return null;
        }
    }
}
