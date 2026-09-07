<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

final class RabbitMQTopology
{
    public function setup(): void
    {
        $connection = app(RabbitMQConnection::class)->connect();

        $channel = $connection->channel();

        /*
        |--------------------------------------------------------------------------
        | Main Exchange
        |--------------------------------------------------------------------------
        */

        $channel->exchange_declare(
            'modular.events',
            'topic',
            false,
            true,
            false
        );

        /*
        |--------------------------------------------------------------------------
        | Dead Letter Exchange
        |--------------------------------------------------------------------------
        */

        $channel->exchange_declare(
            'test.dlx',
            'direct',
            false,
            true,
            false
        );

        /*
        |--------------------------------------------------------------------------
        | Final Dead Letter Queue
        |--------------------------------------------------------------------------
        */

        $channel->queue_declare(
            'test.dlq',
            false,
            true,
            false,
            false
        );

        $channel->queue_bind(
            'test.dlq',
            'test.dlx',
            'test.dead'
        );

        /*
        |--------------------------------------------------------------------------
        | Main Queue
        |--------------------------------------------------------------------------
        */

        $channel->queue_declare(
            'test.queue',
            false,
            true,
            false,
            false,
            false,
            [
                'x-dead-letter-exchange' => [
                    'S',
                    'test.dlx',
                ],
                'x-dead-letter-routing-key' => [
                    'S',
                    'test.dead',
                ],
            ]
        );

        $channel->queue_bind(
            'test.queue',
            'modular.events',
            'test.message'
        );

        /*
        |--------------------------------------------------------------------------
        | Retry Exchange
        |--------------------------------------------------------------------------
        */

        $channel->exchange_declare(
            'test.retry.exchange',
            'direct',
            false,
            true,
            false
        );

        /*
        |--------------------------------------------------------------------------
        | Retry Queue
        |--------------------------------------------------------------------------
        |
        | Message stays here for 5 seconds.
        | After TTL expires, RabbitMQ sends it back to
        | modular.events using test.message.
        |
        */

        $channel->queue_declare(
            'test.retry',
            false,
            true,
            false,
            false,
            false,
            [
                'x-message-ttl' => [
                    'I',
                    5000,
                ],
                'x-dead-letter-exchange' => [
                    'S',
                    'modular.events',
                ],
                'x-dead-letter-routing-key' => [
                    'S',
                    'test.message',
                ],
            ]
        );

        $channel->queue_bind(
            'test.retry',
            'test.retry.exchange',
            'test.retry'
        );

        $channel->close();
        $connection->close();
    }
}