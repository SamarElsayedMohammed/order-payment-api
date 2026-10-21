<?php

namespace Modules\Payment\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Payment\Models\Payment;
use Modules\Payment\Observers\PaymentObserver;
use Modules\Payment\Policies\PaymentPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Payment';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payment';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Payment::class, PaymentPolicy::class);
        Payment::observe(PaymentObserver::class);
    }
}
