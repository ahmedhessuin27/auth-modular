<?php

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQPublisher;
use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;
use App\Modules\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQConsumer;
use App\Modules\Shared\Application\Messaging\Contracts\MessageTopologyInterface;
use App\Modules\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQTopology;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            MessagePublisherInterface::class,
            RabbitMQPublisher::class
        );
        $this->app->singleton(
            MessageConsumerInterface::class,
            RabbitMQConsumer::class
        );
        $this->app->singleton(
            MessageTopologyInterface::class,
        RabbitMQTopology::class
    );
    }

    public function boot(): void
    {
        //
    }
}