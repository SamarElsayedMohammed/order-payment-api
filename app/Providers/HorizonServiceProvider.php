<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Modules\Core\Support\DeveloperToolsAuthenticator;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function (Request $request): bool {
            return DeveloperToolsAuthenticator::passes($request);
        });
    }

    /**
     * Access is decided by Horizon::auth above, not by the default viewHorizon gate.
     */
    protected function gate(): void {}
}
