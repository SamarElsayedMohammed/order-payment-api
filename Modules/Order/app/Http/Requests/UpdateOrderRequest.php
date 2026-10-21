<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Requests\BaseFormRequest;
use Modules\Order\Enums\OrderStatus;

class UpdateOrderRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::OrderUpdateValidationFailed;
    }

    protected function prepareForValidation(): void
    {
        $this->stripClientFields(['total', 'user_id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'email', 'max:255'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:10000'],
            'items.*.price' => ['required_with:items', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
        ];
    }
}
