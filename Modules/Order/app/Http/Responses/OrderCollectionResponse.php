<?php

declare(strict_types=1);

namespace Modules\Order\Http\Responses;

use Modules\Core\Http\Responses\PaginatedResponse;
use Modules\Core\Support\SuccessCode;

class OrderCollectionResponse extends PaginatedResponse
{
    /**
     * @param  array<int, mixed>  $orders
     * @param  array<string, mixed>  $meta
     */
    public function __construct(array $orders, array $meta)
    {
        parent::__construct(
            data: $orders,
            meta: $meta,
            message: 'Orders retrieved successfully.',
            code: SuccessCode::ORDER_LIST,
        );
    }
}
