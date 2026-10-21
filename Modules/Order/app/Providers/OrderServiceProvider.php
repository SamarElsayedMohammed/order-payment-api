<?php

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Order\Models\Order;
use Modules\Order\Observers\OrderObserver;
use Modules\Order\Policies\OrderPolicy;
use Modules\Core\Providers\BaseModuleServiceProvider;

class OrderServiceProvider extends BaseModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Order';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'order';

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

        Gate::policy(Order::class, OrderPolicy::class);
        Order::observe(OrderObserver::class);
    }
}
