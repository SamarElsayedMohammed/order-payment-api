<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    public function test_it_builds_the_standard_error_envelope(): void
    {
        $request = Request::create('/api/v1/orders/1');
        $request->attributes->set('trace_id', 'trace-12345678');

        $response = (new ErrorResponse(ErrorCode::OrderNotFound))->toResponse($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'trace_code' => 'ORD-SHW-001',
            'code' => '404',
            'message' => 'The order was not found.',
            'trace_id' => 'trace-12345678',
            'data' => null,
            'meta' => null,
        ], $response->getData(true));
    }

    public function test_validation_errors_are_included_only_when_present(): void
    {
        $request = Request::create('/api/v1/orders');

        $withErrors = (new ErrorResponse(ErrorCode::OrderCreateValidationFailed, errors: ['items' => ['required']]))
            ->toResponse($request)
            ->getData(true);

        $this->assertSame(['items' => ['required']], $withErrors['errors']);
        $this->assertArrayNotHasKey('errors', (new ErrorResponse(ErrorCode::NotFound))->toResponse($request)->getData(true));
    }

    public function test_logged_writes_a_masked_warning_with_the_error_code(): void
    {
        $channel = Mockery::mock();
        $channel->shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Payments can only be processed for confirmed orders.'
                    && $context['error_code'] === 'PAY-PRC-002'
                    && $context['card_number'] === '************4242';
            });

        Log::shouldReceive('channel')->with('daily')->andReturn($channel);

        ErrorResponse::logged(ErrorCode::PaymentOrderNotConfirmed, context: ['card_number' => '4242424242424242']);
    }

    public function test_plain_constructor_does_not_write_to_the_log(): void
    {
        Log::shouldReceive('channel')->never();

        new ErrorResponse(ErrorCode::Forbidden);
    }
}
