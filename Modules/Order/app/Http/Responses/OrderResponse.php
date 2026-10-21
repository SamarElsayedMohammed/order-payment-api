<?php

declare(strict_types=1);

namespace Modules\Order\Http\Responses;

use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;
use Modules\Order\Http\Resources\OrderResource;

class OrderResponse extends SuccessResponse
{
    public function __construct(OrderResource $order, string $message, string $code, int $httpStatus = 200)
    {
        parent::__construct(
            data: $order,
            message: $message,
            code: $code,
            httpStatus: $httpStatus,
        );
    }

    public static function created(OrderResource $order): self
    {
        return new self($order, 'Order created successfully.', SuccessCode::ORDER_CREATED, 201);
    }

    public static function updated(OrderResource $order): self
    {
        return new self($order, 'Order updated successfully.', SuccessCode::ORDER_UPDATED);
    }

    public static function show(OrderResource $order): self
    {
        return new self($order, 'Order retrieved successfully.', SuccessCode::ORDER_SHOW);
    }
}
