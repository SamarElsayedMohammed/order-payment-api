<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Requests\BaseFormRequest;

class ProcessPaymentRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::PaymentValidationFailed;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'payment_method' => ['required', 'string', Rule::in(array_keys(config('payment.gateways', [])))],
            'simulate_outcome' => ['sometimes', Rule::in(['success', 'failed'])],
            'card_number' => ['sometimes', 'nullable', 'string', 'max:19'],
            'cvv' => ['sometimes', 'nullable', 'string', 'max:4'],
            'payer_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
