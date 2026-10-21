<?php

declare(strict_types=1);

use Modules\Payment\Gateways\CreditCardGateway;
use Modules\Payment\Gateways\PayPalGateway;

return [
    'currency' => env('PAYMENT_CURRENCY', 'USD'),

    'gateways' => [
        'credit_card' => [
            'class' => CreditCardGateway::class,
            'display_name' => 'Credit / Debit Card',
            'enabled' => env('CREDIT_CARD_ENABLED', true),
            'simulate' => env('CREDIT_CARD_SIMULATE', false),
            'api_key' => env('CREDIT_CARD_API_KEY'),
            'secret_key' => env('CREDIT_CARD_SECRET'),
            'base_url' => env('CREDIT_CARD_BASE_URL', 'https://sandbox.card-gateway.test'),
            'timeout' => env('CREDIT_CARD_TIMEOUT', 15),
            'currency' => env('PAYMENT_CURRENCY', 'USD'),
        ],
        'paypal' => [
            'class' => PayPalGateway::class,
            'display_name' => 'PayPal',
            'enabled' => env('PAYPAL_ENABLED', true),
            'simulate' => env('PAYPAL_SIMULATE', false),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_SECRET'),
            'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
            'timeout' => env('PAYPAL_TIMEOUT', 15),
            'currency' => env('PAYMENT_CURRENCY', 'USD'),
        ],
    ],
];
