<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers;

use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;
use Modules\Core\Http\Controllers\BaseController;
use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;
use Modules\Payment\Gateways\PaymentGatewayFactory;
use Modules\Payment\Http\Requests\ListPaymentRequest;
use Modules\Payment\Http\Requests\ProcessPaymentRequest;
use Modules\Payment\Http\Resources\PaymentResource;
use Modules\Payment\Http\Responses\PaymentCollectionResponse;
use Modules\Payment\Http\Responses\PaymentResponse;
use Modules\Payment\Services\PaymentService;

class PaymentController extends BaseController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayFactory $gateways,
    ) {}

    /**
     * List enabled payment gateways from the configuration registry.
     *
     * Possible errors: AUTH-TKN-001.
     */
    public function methods(): SuccessResponse
    {
        return new SuccessResponse(
            data: $this->gateways->enabledMethods(),
            message: 'Payment methods retrieved successfully.',
            code: SuccessCode::PAYMENT_METHODS,
        );
    }

    /**
     * List the authenticated user's payments.
     *
     * Filter with status, method (or payment_method), and order_id.
     * Offset pagination is the default. Send pagination=cursor for large lists.
     *
     * Possible errors: PAY-LST-001, AUTH-TKN-001.
     */
    public function index(ListPaymentRequest $request): PaymentCollectionResponse
    {
        $result = $this->payments->list(
            $this->currentUser(),
            $request->validated(),
            $this->resolvePerPage($request),
        );

        return new PaymentCollectionResponse($result['data'], $result['meta']);
    }

    /**
     * List payments that belong to one owned order.
     *
     * Possible errors: ORD-SHW-001, SYS-GEN-005, PAY-LST-001, AUTH-TKN-001.
     */
    public function forOrder(ListPaymentRequest $request, int $order): PaymentCollectionResponse
    {
        $result = $this->payments->listForOrder(
            $this->currentUser(),
            $order,
            $request->validated(),
            $this->resolvePerPage($request),
        );

        return new PaymentCollectionResponse($result['data'], $result['meta']);
    }

    /**
     * Show one payment owned by the authenticated user.
     *
     * Possible errors: PAY-SHW-001, SYS-GEN-005, AUTH-TKN-001.
     */
    public function show(int $payment): PaymentResponse
    {
        $model = $this->payments->show($this->currentUser(), $payment);
        $this->authorize('view', $model);

        return PaymentResponse::show(new PaymentResource($model));
    }

    /**
     * Process a payment for a confirmed order.
     *
     * The order row is locked for the duration of the charge. A failed charge is still stored.
     * Send an Idempotency-Key header to safely retry the same request.
     * Possible errors: PAY-PRC-001, PAY-PRC-002, PAY-PRC-003, PAY-PRC-004, PAY-PRC-005, PAY-PRC-006, ORD-SHW-001, SYS-GEN-005, AUTH-TKN-001.
     */
    public function store(ProcessPaymentRequest $request, int $order): PaymentResponse
    {
        $attempt = $this->payments->process(
            $this->currentUser(),
            $order,
            $request->safe()->except('idempotency_key'),
            $request->validated('idempotency_key'),
        );

        if ($attempt->failed) {
            throw new ApiBusinessException(
                ErrorCode::PaymentGatewayFailed,
                data: (new PaymentResource($attempt->payment))->resolve($request),
            );
        }

        return PaymentResponse::processed(new PaymentResource($attempt->payment), $attempt->replay);
    }
}
