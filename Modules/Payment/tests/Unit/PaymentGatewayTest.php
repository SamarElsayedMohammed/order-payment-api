<?php

declare(strict_types=1);

namespace Modules\Payment\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;
use Modules\Order\Models\Order;
use Modules\Payment\Gateways\CreditCardGateway;
use Modules\Payment\Gateways\PaymentGatewayFactory;
use Modules\Payment\Gateways\PayPalGateway;
use Modules\Payment\Http\Clients\GatewayHttpClient;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    public function test_factory_rejects_unknown_and_disabled_gateways(): void
    {
        $factory = app(PaymentGatewayFactory::class);

        try {
            $factory->make('stripe');
            $this->fail('Unknown gateway should be rejected.');
        } catch (ApiBusinessException $exception) {
            $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $exception->errorCode);
        }

        config()->set('payment.gateways.credit_card.enabled', false);

        try {
            $factory->make('credit_card');
            $this->fail('Disabled gateway should be rejected.');
        } catch (ApiBusinessException $exception) {
            $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $exception->errorCode);
        }
    }

    public function test_credit_card_gateway_charges_and_declines_through_http(): void
    {
        Http::fake([
            'https://sandbox.card-gateway.test/v1/charges' => Http::response(['id' => 'CC-100', 'auth_code' => 'A1'], 200),
        ]);

        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), $this->liveCardConfig());
        $order = (new Order)->forceFill(['id' => 5, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $result = $gateway->process($order, ['card_number' => '4242424242424242', 'cvv' => '123']);

        $this->assertTrue($result->successful);
        $this->assertSame('CC-100', $result->transactionId);
        Http::assertSentCount(1);
    }

    public function test_credit_card_gateway_returns_a_failed_result_when_the_issuer_declines(): void
    {
        Http::fake([
            'https://sandbox.card-gateway.test/v1/charges' => Http::response(['id' => 'CC-101', 'reason' => 'Card declined'], 402),
        ]);

        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), $this->liveCardConfig());
        $order = (new Order)->forceFill(['id' => 6, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $result = $gateway->process($order, ['card_number' => '4000000000000002', 'cvv' => '123']);

        $this->assertFalse($result->successful);
        $this->assertSame('Card declined', $result->failureReason);
    }

    public function test_paypal_gateway_fetches_an_oauth_token_before_charging(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token', 'expires_in' => 3600], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'PAYPAL-1', 'status' => 'COMPLETED'], 201),
        ]);

        $gateway = new PayPalGateway(app(GatewayHttpClient::class), [
            'enabled' => true,
            'simulate' => false,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'base_url' => 'https://api-m.sandbox.paypal.com',
            'display_name' => 'PayPal',
            'currency' => 'USD',
        ]);
        $order = (new Order)->forceFill(['id' => 7, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $result = $gateway->process($order, ['payer_email' => 'buyer@example.com']);

        $this->assertTrue($result->successful);
        $this->assertSame('PAYPAL-1', $result->transactionId);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v1/oauth2/token' && $request->method() === 'POST');

        $gateway->process($order, ['payer_email' => 'buyer@example.com']);

        Http::assertSentCount(3);
    }

    public function test_post_charge_is_sent_once_when_the_connection_fails(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts): never {
            $attempts++;

            throw new ConnectionException('timeout');
        });

        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), $this->liveCardConfig());
        $order = (new Order)->forceFill(['id' => 8, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        try {
            $gateway->process($order, ['card_number' => '4242424242424242', 'cvv' => '123']);
            $this->fail('A connection failure should be reported as a gateway failure.');
        } catch (ApiBusinessException $exception) {
            $this->assertSame(ErrorCode::PaymentGatewayFailed, $exception->errorCode);
        }

        $this->assertSame(1, $attempts);
    }

    public function test_simulation_is_off_unless_configured(): void
    {
        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), [
            'enabled' => true,
            'base_url' => 'https://sandbox.card-gateway.test',
        ]);
        $order = (new Order)->forceFill(['id' => 9, 'total' => '15.00']);

        try {
            $gateway->process($order, ['card_number' => '4242424242424242', 'cvv' => '123']);
            $this->fail('A live gateway without keys should be rejected.');
        } catch (ApiBusinessException $exception) {
            $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $exception->errorCode);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function liveCardConfig(): array
    {
        return [
            'enabled' => true,
            'simulate' => false,
            'api_key' => 'card-key',
            'secret_key' => 'card-secret',
            'base_url' => 'https://sandbox.card-gateway.test',
            'display_name' => 'Credit / Debit Card',
            'currency' => 'USD',
        ];
    }
}
