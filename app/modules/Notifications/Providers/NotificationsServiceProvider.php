<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Auth\IntegrationEvents\UserRegistered;
use App\Modules\Notifications\Application\Handlers\UserRegisteredHandler;
use App\Modules\Shared\Application\Messaging\MessageSubscriptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public const QUEUE = 'notifications.events.queue';

    /**
     * Bind Domain contracts to their Infrastructure implementations.
     */
    public function register(): void
    {
        // $this->app->bind(
        //     \App\Modules\Notifications\Domain\Contracts\SomeContract::class,
        //     \App\Modules\Notifications\Infrastructure\Repositories\SomeImplementation::class,
        // );

        // Events this module reacts to. The queue is bound to exactly these
        // names by rabbitmq:setup and consumed via `rabbitmq:consume`.
        $this->callAfterResolving(
            MessageSubscriptions::class,
            function (MessageSubscriptions $subscriptions): void {
                $subscriptions->subscribe(self::QUEUE, [
                    UserRegistered::NAME => UserRegisteredHandler::class,
                ]);
            }
        );
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
