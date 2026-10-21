<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Support\DeveloperToolsAuthenticator;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        JsonResource::withoutWrapping();

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        Scramble::configure()
            ->expose(
                ui: fn ($router, $action) => $router->get('/docs', $action)->name('scramble.docs.ui'),
                document: fn ($router, $action) => $router->get('/docs/openapi.json', $action)->name('scramble.docs.document'),
            );

        LogViewer::auth(function (Request $request): bool {
            return DeveloperToolsAuthenticator::passes($request);
        });
    }
}
