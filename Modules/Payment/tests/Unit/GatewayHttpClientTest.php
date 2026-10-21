<?php

declare(strict_types=1);

namespace Modules\Payment\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Modules\Payment\Http\Clients\GatewayHttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GatewayHttpClientTest extends TestCase
{
    public function test_post_charges_are_not_retried(): void
    {
        $client = new GatewayHttpClient;

        $this->assertFalse($client->shouldRetry('POST', new ConnectionException('timeout')));
        $this->assertFalse($client->shouldRetry('post', new RuntimeException('no')));
    }

    public function test_get_requests_retry_only_on_connection_errors(): void
    {
        $client = new GatewayHttpClient;

        $this->assertTrue($client->shouldRetry('GET', new ConnectionException('timeout')));
        $this->assertFalse($client->shouldRetry('GET', new RuntimeException('bad response')));
    }
}
