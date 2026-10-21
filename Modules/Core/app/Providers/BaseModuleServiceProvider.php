<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

abstract class BaseModuleServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->mergeModuleConfig();
    }

    /**
     * Module config is merged in register() instead, under the file name as the key,
     * so framework keys such as auth and jwt are in place before any provider boots.
     */
    protected function registerConfig(): void {}

    private function mergeModuleConfig(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        foreach (glob(module_path($this->name, 'config/*.php')) ?: [] as $file) {
            $key = basename($file, '.php');

            config([$key => array_replace_recursive(config($key, []), require $file)]);
        }
    }
}
