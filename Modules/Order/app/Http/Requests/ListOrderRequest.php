<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Enums\PerPage;
use Modules\Core\Http\Requests\BaseFormRequest;
use Modules\Order\Enums\OrderStatus;

class ListOrderRequest extends BaseFormRequest
{
    protected function errorCode(): ErrorCode
    {
        return ErrorCode::OrderListValidationFailed;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(OrderStatus::class)],
            'per_page' => PerPage::rules(),
            'page' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'pagination' => ['sometimes', Rule::in(['offset', 'cursor'])],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
