<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Domain\Contracts\IdentityTokenVerifier;
use App\Modules\Auth\Infrastructure\Services\OidcIdentityTokenVerifier;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bind Domain contracts to their Infrastructure implementations.
     */
    public function register(): void
    {
        $this->app->bind(
            IdentityTokenVerifier::class,
            OidcIdentityTokenVerifier::class,
        );
    }

    /**
     * Wire up the module's routes, migrations and other resources.
     */
    public function boot(): void
    {
        $this->registerRoutes();

        // Keep module-owned migrations inside the module:
        // $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Database/Migrations');
    }

    protected function registerRoutes(): void
    {

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../Routes/api.php');
    }
}
