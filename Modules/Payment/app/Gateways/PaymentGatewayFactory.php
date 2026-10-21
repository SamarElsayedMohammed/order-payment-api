<?php

declare(strict_types=1);

namespace Modules\Payment\Gateways;

use Illuminate\Contracts\Container\Container;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Payment\Contracts\PaymentGatewayInterface;

class PaymentGatewayFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $method): PaymentGatewayInterface|ErrorResponse
    {
        $gateways = config('payment.gateways', []);
        $config = $gateways[$method] ?? null;

        if (! is_array($config) || ! ($config['enabled'] ?? false)) {
            return ErrorResponse::logged(ErrorCode::PaymentGatewayUnsupported);
        }

        $class = $config['class'] ?? null;

        if (! is_string($class) || ! is_subclass_of($class, PaymentGatewayInterface::class)) {
            return ErrorResponse::logged(ErrorCode::PaymentGatewayUnsupported);
        }

        $gateway = $this->container->make($class, ['config' => $config]);

        if (! $gateway instanceof PaymentGatewayInterface) {
            return ErrorResponse::logged(ErrorCode::PaymentGatewayUnsupported);
        }

        return $gateway;
    }

    /**
     * @return list<array{key: string, name: string}>
     */
    public function enabledMethods(): array
    {
        $methods = [];

        foreach (config('payment.gateways', []) as $key => $config) {
            if (! is_array($config) || ! ($config['enabled'] ?? false)) {
                continue;
            }

            $methods[] = [
                'key' => (string) $key,
                'name' => (string) ($config['display_name'] ?? $key),
            ];
        }

        return $methods;
    }
}
