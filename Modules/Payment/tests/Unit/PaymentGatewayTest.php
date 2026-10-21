<?php

declare(strict_types=1);

namespace Modules\Payment\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Order\Models\Order;
use Modules\Payment\DTOs\PaymentResultData;
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

        $unknown = $factory->make('stripe');
        $this->assertInstanceOf(ErrorResponse::class, $unknown);
        $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $unknown->errorCode);

        config()->set('payment.gateways.credit_card.enabled', false);

        $disabled = $factory->make('credit_card');
        $this->assertInstanceOf(ErrorResponse::class, $disabled);
        $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $disabled->errorCode);
    }

    public function test_factory_rejects_a_configured_class_that_is_not_a_gateway(): void
    {
        config()->set('payment.gateways.broken', ['enabled' => true, 'class' => \stdClass::class]);

        $result = app(PaymentGatewayFactory::class)->make('broken');

        $this->assertInstanceOf(ErrorResponse::class, $result);
        $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $result->errorCode);
    }

    public function test_factory_lists_only_enabled_methods(): void
    {
        config()->set('payment.gateways.paypal.enabled', false);

        $this->assertSame(
            [['key' => 'credit_card', 'name' => 'Credit / Debit Card']],
            app(PaymentGatewayFactory::class)->enabledMethods(),
        );
    }

    public function test_simulated_gateways_succeed_without_any_http_call(): void
    {
        Http::fake();
        $order = (new Order)->forceFill(['id' => 14, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $card = new CreditCardGateway(app(GatewayHttpClient::class), ['enabled' => true, 'simulate' => true]);
        $paypal = new PayPalGateway(app(GatewayHttpClient::class), ['enabled' => true, 'simulate' => true]);

        $this->assertTrue($card->process($order, ['card_number' => '4242424242424242'])->successful);
        $this->assertTrue($paypal->process($order)->successful);
        $this->assertFalse($paypal->process($order, ['simulate_outcome' => 'failed'])->successful);
        Http::assertNothingSent();
    }

    public function test_credit_card_gateway_charges_and_declines_through_http(): void
    {
        Http::fake([
            'https://sandbox.card-gateway.test/v1/charges' => Http::response(['id' => 'CC-100', 'auth_code' => 'A1'], 200),
        ]);

        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), $this->liveCardConfig());
        $order = (new Order)->forceFill(['id' => 5, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $result = $gateway->process($order, ['card_number' => '4242424242424242', 'cvv' => '123']);

        $this->assertInstanceOf(PaymentResultData::class, $result);
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

        $this->assertInstanceOf(PaymentResultData::class, $result);
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

        $this->assertInstanceOf(PaymentResultData::class, $result);
        $this->assertTrue($result->successful);
        $this->assertSame('PAYPAL-1', $result->transactionId);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v1/oauth2/token' && $request->method() === 'POST');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            && $request['intent'] === 'CAPTURE'
            && $request['purchase_units'][0]['reference_id'] === '7'
            && $request['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '15.00']);

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

        $result = $gateway->process($order, ['card_number' => '4242424242424242', 'cvv' => '123']);
        $this->assertInstanceOf(ErrorResponse::class, $result);
        $this->assertSame(ErrorCode::PaymentGatewayFailed, $result->errorCode);

        $this->assertSame(1, $attempts);
    }

    public function test_simulation_is_off_unless_configured(): void
    {
        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), [
            'enabled' => true,
            'base_url' => 'https://sandbox.card-gateway.test',
        ]);
        $order = (new Order)->forceFill(['id' => 9, 'total' => '15.00']);

        $result = $gateway->process($order, ['card_number' => '4242424242424242', 'cvv' => '123']);
        $this->assertInstanceOf(ErrorResponse::class, $result);
        $this->assertSame(ErrorCode::PaymentGatewayUnsupported, $result->errorCode);
    }

    public function test_credit_card_live_mode_requires_card_number_and_cvv(): void
    {
        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), $this->liveCardConfig());
        $order = (new Order)->forceFill(['id' => 10, 'total' => '15.00']);

        $missingCard = $gateway->process($order, ['cvv' => '123']);
        $this->assertInstanceOf(ErrorResponse::class, $missingCard);
        $this->assertSame(ErrorCode::PaymentValidationFailed, $missingCard->errorCode);

        $missingCvv = $gateway->process($order, ['card_number' => '4242424242424242']);
        $this->assertInstanceOf(ErrorResponse::class, $missingCvv);
        $this->assertSame(ErrorCode::PaymentValidationFailed, $missingCvv->errorCode);
    }

    public function test_credit_card_simulation_declines_cards_ending_with_0000(): void
    {
        $gateway = new CreditCardGateway(app(GatewayHttpClient::class), [
            'enabled' => true,
            'simulate' => true,
        ]);
        $order = (new Order)->forceFill(['id' => 11, 'total' => '15.00']);

        $declined = $gateway->process($order, ['card_number' => '4242424242420000']);
        $this->assertInstanceOf(PaymentResultData::class, $declined);
        $this->assertFalse($declined->successful);
        $this->assertSame('Card declined by issuer', $declined->failureReason);
    }

    public function test_paypal_gateway_refreshes_token_and_retries_once_on_401(): void
    {
        $chargeCalls = 0;

        Http::fake(function ($request) use (&$chargeCalls) {
            if ($request->url() === 'https://api-m.sandbox.paypal.com/v1/oauth2/token') {
                return Http::response(['access_token' => 'token-'.($chargeCalls + 1), 'expires_in' => 3600], 200);
            }

            $chargeCalls++;

            if ($chargeCalls === 1) {
                return Http::response(['message' => 'Unauthorized'], 401);
            }

            return Http::response(['id' => 'PAYPAL-RETRY-OK', 'status' => 'COMPLETED'], 201);
        });

        $gateway = new PayPalGateway(app(GatewayHttpClient::class), [
            'enabled' => true,
            'simulate' => false,
            'client_id' => 'client-retry-id',
            'client_secret' => 'client-secret',
            'base_url' => 'https://api-m.sandbox.paypal.com',
        ]);
        $order = (new Order)->forceFill(['id' => 12, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $result = $gateway->process($order);

        $this->assertInstanceOf(PaymentResultData::class, $result);
        $this->assertTrue($result->successful);
        $this->assertSame('PAYPAL-RETRY-OK', $result->transactionId);
        $this->assertSame(2, $chargeCalls);
    }

    public function test_paypal_gateway_returns_error_response_when_oauth_fails(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $gateway = new PayPalGateway(app(GatewayHttpClient::class), [
            'enabled' => true,
            'simulate' => false,
            'client_id' => 'bad-client-id',
            'client_secret' => 'bad-secret',
            'base_url' => 'https://api-m.sandbox.paypal.com',
        ]);
        $order = (new Order)->forceFill(['id' => 13, 'total' => '15.00', 'customer_email' => 'buyer@example.com']);

        $result = $gateway->process($order);

        $this->assertInstanceOf(ErrorResponse::class, $result);
        $this->assertSame(ErrorCode::PaymentGatewayFailed, $result->errorCode);
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
