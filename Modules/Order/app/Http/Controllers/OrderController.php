<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers;

use Modules\Core\Http\Controllers\BaseController;
use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;
use Modules\Order\Http\Requests\ListOrderRequest;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Order\Http\Requests\UpdateOrderRequest;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Http\Responses\OrderCollectionResponse;
use Modules\Order\Http\Responses\OrderResponse;
use Modules\Order\Services\OrderService;

class OrderController extends BaseController
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * List the authenticated user's orders.
     *
     * Filter with status, from, and to. Offset pagination is the default. Send pagination=cursor for large lists.
     * Item lines are omitted from this response and returned by the show endpoint.
     * Results are cached per user for 60 seconds.
     *
     * Possible errors: ORD-LST-001, AUTH-TKN-001.
     */
    public function index(ListOrderRequest $request): OrderCollectionResponse
    {
        $result = $this->orders->list(
            $this->currentUser(),
            $request->validated(),
            $this->resolvePerPage($request),
        );

        return new OrderCollectionResponse($result['data'], $result['meta']);
    }

    /**
     * Create a pending order. The total is calculated on the server from the submitted items.
     *
     * A client-supplied total or user id is ignored.
     *
     * Possible errors: ORD-CRT-001, AUTH-TKN-001.
     */
    public function store(StoreOrderRequest $request): OrderResponse
    {
        $order = $this->orders->create($this->currentUser(), $request->validated());

        return OrderResponse::created(new OrderResource($order));
    }

    /**
     * Show one order owned by the authenticated user.
     *
     * Possible errors: ORD-SHW-001, SYS-GEN-005, AUTH-TKN-001.
     */
    public function show(int $order): OrderResponse
    {
        $model = $this->orders->show($this->currentUser(), $order);
        $this->authorize('view', $model);

        return OrderResponse::show(new OrderResource($model));
    }

    /**
     * Update an order.
     *
     * Items can change only while the order is pending (ORD-UPD-002).
     * Status moves pending -> confirmed|cancelled, and confirmed -> cancelled when no successful payment exists (ORD-UPD-003).
     *
     * Possible errors: ORD-UPD-001, ORD-UPD-002, ORD-UPD-003, ORD-SHW-001, SYS-GEN-005, AUTH-TKN-001.
     */
    public function update(UpdateOrderRequest $request, int $order): OrderResponse
    {
        $this->authorize('update', $this->orders->show($this->currentUser(), $order));
        $model = $this->orders->update($this->currentUser(), $order, $request->validated());

        return OrderResponse::updated(new OrderResource($model));
    }

    /**
     * Delete an order that has no associated payments, including failed payments.
     *
     * Possible errors: ORD-DEL-001, ORD-SHW-001, SYS-GEN-005, AUTH-TKN-001.
     */
    public function destroy(int $order): SuccessResponse
    {
        $this->authorize('delete', $this->orders->show($this->currentUser(), $order));
        $this->orders->delete($this->currentUser(), $order);

        return new SuccessResponse(
            data: null,
            message: 'Order deleted successfully.',
            code: SuccessCode::ORDER_DELETED,
        );
    }
}
