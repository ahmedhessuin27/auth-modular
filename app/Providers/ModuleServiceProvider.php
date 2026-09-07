<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Discovers every module under app/Modules and registers its service
 * providers (any class matching App\Modules\{Module}\Providers\*ServiceProvider).
 *
 * Add a new module by creating its folder + a provider — no wiring here.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->discoverModuleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function discoverModuleProviders(): array
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return [];
        }

        $providers = [];

        foreach (glob($modulesPath.'/*/Providers/*ServiceProvider.php') ?: [] as $file) {
            $relative = str_replace([$modulesPath.DIRECTORY_SEPARATOR, '.php'], '', $file);
            $class = 'App\\Modules\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (class_exists($class)) {
                $providers[] = $class;
            }
        }

        sort($providers);

        return $providers;
    }
}
