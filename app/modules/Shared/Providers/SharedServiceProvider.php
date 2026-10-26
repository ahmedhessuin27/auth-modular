<?php

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\MessageSubscriptions;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxPublisher;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxRelay;
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
        // Application code publishes into the outbox...
        $this->app->singleton(
            MessagePublisherInterface::class,
            OutboxPublisher::class
        );

        // ...and only the relay talks to the broker directly. Singleton so
        // the relay process reuses one connection.
        $this->app->singleton(RabbitMQPublisher::class);
        $this->app->when(OutboxRelay::class)
            ->needs(MessagePublisherInterface::class)
            ->give(RabbitMQPublisher::class);

        // Modules add their subscriptions to this from their own providers.
        $this->app->singleton(MessageSubscriptions::class);

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
