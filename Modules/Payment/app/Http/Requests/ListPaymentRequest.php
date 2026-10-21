<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Requests\BaseFormRequest;
use Modules\Payment\Enums\PaymentStatus;

class ListPaymentRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::PaymentListValidationFailed;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('method') && ! $this->filled('payment_method')) {
            $this->merge([
                'payment_method' => $this->input('method'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(config('payment.gateways', [])))],
            'order_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'pagination' => ['sometimes', Rule::in(['offset', 'cursor'])],
        ];
    }
}
