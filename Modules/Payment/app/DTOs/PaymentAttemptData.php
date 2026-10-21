<?php

declare(strict_types=1);

namespace Modules\Payment\DTOs;

use Modules\Payment\Models\Payment;

final class PaymentAttemptData
{
    public function __construct(
        public readonly Payment $payment,
        public readonly bool $replay,
        public readonly bool $failed,
    ) {}
}
