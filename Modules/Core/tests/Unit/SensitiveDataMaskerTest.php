<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit;

use Modules\Core\Support\SensitiveDataMasker;
use PHPUnit\Framework\TestCase;

class SensitiveDataMaskerTest extends TestCase
{
    public function test_card_numbers_keep_only_the_last_four_digits(): void
    {
        $masked = (new SensitiveDataMasker)->mask([
            'card_number' => '4242424242424242',
            'cardNumber' => '4000 0000 0000 0002',
            'amount' => '10.00',
        ]);

        $this->assertSame('************4242', $masked['card_number']);
        $this->assertSame('************0002', $masked['cardNumber']);
        $this->assertSame('10.00', $masked['amount']);
    }

    public function test_secrets_are_fully_redacted_in_any_shape(): void
    {
        $masked = (new SensitiveDataMasker)->mask([
            'cvv' => '123',
            'password' => 'password123',
            'new_password' => 'password456',
            'nested' => ['client_secret' => 'super-secret'],
            'X-Api-Key' => 'card-key',
            'access_token' => 'A21AAF-token-value',
            'authorization' => ['Bearer abc.def.ghi'],
            'token_type' => 'Bearer',
        ]);

        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['cvv']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['password']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['new_password']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['nested']['client_secret']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['X-Api-Key']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['access_token']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['authorization']);
        $this->assertSame(SensitiveDataMasker::REDACTED, $masked['token_type']);
    }
}
