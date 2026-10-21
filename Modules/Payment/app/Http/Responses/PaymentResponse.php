<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Responses;

use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;
use Modules\Payment\Http\Resources\PaymentResource;

class PaymentResponse extends SuccessResponse
{
    public function __construct(PaymentResource $payment, string $message, string $code, int $httpStatus = 200)
    {
        parent::__construct(
            data: $payment,
            message: $message,
            code: $code,
            httpStatus: $httpStatus,
        );
    }

    public static function processed(PaymentResource $payment, bool $replay): self
    {
        return new self(
            $payment,
            $replay ? 'Existing payment returned for this idempotency key.' : 'Payment processed successfully.',
            SuccessCode::PAYMENT_PROCESSED,
            $replay ? 200 : 201,
        );
    }

    public static function show(PaymentResource $payment): self
    {
        return new self($payment, 'Payment retrieved successfully.', SuccessCode::PAYMENT_SHOW);
    }
}
