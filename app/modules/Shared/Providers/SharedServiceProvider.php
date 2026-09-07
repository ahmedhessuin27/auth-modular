<?php

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQPublisher;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            MessagePublisherInterface::class,
            RabbitMQPublisher::class
        );
    }

    public function boot(): void
    {
        //
    }
}