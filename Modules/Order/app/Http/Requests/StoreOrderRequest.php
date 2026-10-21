<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests;

use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Requests\BaseFormRequest;

class StoreOrderRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::OrderCreateValidationFailed;
    }

    protected function prepareForValidation(): void
    {
        $this->stripClientFields(['total', 'user_id', 'status']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
        ];
    }
}
