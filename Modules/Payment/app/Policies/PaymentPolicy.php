<?php

declare(strict_types=1);

namespace Modules\Payment\Policies;

use App\Models\User;
use Modules\Payment\Models\Payment;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $user->id === $payment->user_id;
    }
}
