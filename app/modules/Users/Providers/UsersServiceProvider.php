<?php

namespace App\Modules\Users\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    /**
     * Bind Domain contracts to their Infrastructure implementations.
     */
    public function register(): void
    {
        // $this->app->bind(
        //     \App\Modules\Users\Domain\Contracts\SomeContract::class,
        //     \App\Modules\Users\Infrastructure\Repositories\SomeImplementation::class,
        // );
    }

    /**
     * Wire up the module's routes, migrations and other resources.
     */
    public function boot(): void
    {
        $this->registerRoutes();

        // $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Database/Migrations');
    }

    protected function registerRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/web.php');

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../Routes/api.php');
    }
}
