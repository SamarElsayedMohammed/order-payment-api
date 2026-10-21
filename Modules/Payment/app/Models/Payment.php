<?php

declare(strict_types=1);

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\BaseModel;
use Modules\Order\Models\Order;
use Modules\Payment\Database\Factories\PaymentFactory;
use Modules\Payment\Enums\PaymentStatus;

class Payment extends BaseModel
{
    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected $fillable = [
        'order_id',
        'user_id',
        'payment_method',
        'status',
        'amount',
        'transaction_id',
        'idempotency_key',
        'payload_hash',
        'failure_reason',
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
