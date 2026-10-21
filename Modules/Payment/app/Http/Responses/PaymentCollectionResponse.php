<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Responses;

use Modules\Core\Http\Responses\PaginatedResponse;
use Modules\Core\Support\SuccessCode;

class PaymentCollectionResponse extends PaginatedResponse
{
    /**
     * @param  array<int, mixed>  $payments
     * @param  array<string, mixed>  $meta
     */
    public function __construct(array $payments, array $meta)
    {
        parent::__construct(
            data: $payments,
            meta: $meta,
            message: 'Payments retrieved successfully.',
            code: SuccessCode::PAYMENT_LIST,
        );
    }
}
