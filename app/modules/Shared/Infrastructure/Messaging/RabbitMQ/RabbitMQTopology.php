<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\Contracts\MessageTopologyInterface;
use App\Modules\Shared\Application\Messaging\QueueTopology;
use PhpAmqpLib\Channel\AMQPChannel;

final class RabbitMQTopology implements MessageTopologyInterface
{
    public function __construct(
        private readonly RabbitMQConnection $rabbitMQConnection,
    ) {
    }

    public function setup(QueueTopology ...$topologies): void
    {
        $connection = $this->rabbitMQConnection->connect();

        $channel = $connection->channel();

        /*
        |--------------------------------------------------------------------------
        | Main Events Exchange
        |--------------------------------------------------------------------------
        */

        $channel->exchange_declare(
            config('rabbitmq.exchange'),
            'topic',
            false,
            true,
            false
        );

        /*
        |--------------------------------------------------------------------------
        | Create Each Queue Topology
        |--------------------------------------------------------------------------
        */

        foreach ($topologies as $topology) {
            $this->createQueueTopology(
                $channel,
                $topology
            );
        }

        $channel->close();
        $connection->close();
    }

    private function createQueueTopology(
        AMQPChannel $channel,
        QueueTopology $topology
    ): void {
        $queue = $topology->queue;

        $this->declareDeadLetterTopology(
            $channel,
            $queue
        );

        $this->declareMainQueue(
            $channel,
            $queue
        );

        $this->declareRetryTopology(
            $channel,
            $queue
        );

        foreach ($topology->bindings as $routingKey) {
            $channel->queue_bind(
                $queue,
                config('rabbitmq.exchange'),
                $routingKey
            );
        }
    }

    private function declareDeadLetterTopology(
        AMQPChannel $channel,
        string $queue
    ): void {
        $dlx = $queue . '.dlx';
        $dlq = $queue . '.dlq';

        $channel->exchange_declare(
            $dlx,
            'direct',
            false,
            true,
            false
        );

        $channel->queue_declare(
            $dlq,
            false,
            true,
            false,
            false
        );

        $channel->queue_bind(
            $dlq,
            $dlx,
            'dead'
        );
    }

    private function declareMainQueue(
        AMQPChannel $channel,
        string $queue
    ): void {
        $dlx = $queue . '.dlx';

        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            [
                'x-dead-letter-exchange' => [
                    'S',
                    $dlx,
                ],
                'x-dead-letter-routing-key' => [
                    'S',
                    'dead',
                ],
            ]
        );
    }

    private function declareRetryTopology(
        AMQPChannel $channel,
        string $queue
    ): void {
        $retryExchange = $queue . '.retry.exchange';
        $retryQueue = $queue . '.retry.queue';

        $channel->exchange_declare(
            $retryExchange,
            'direct',
            false,
            true,
            false
        );

        $channel->queue_declare(
            $retryQueue,
            false,
            true,
            false,
            false,
            false,
            [
                'x-message-ttl' => [
                    'I',
                    config('rabbitmq.retry.delay_ms'),
                ],
                // Expired retries go back through the default exchange
                // straight to the originating queue only. Routing through
                // the events exchange would reuse the "retry" routing key,
                // which nothing binds, and the message would be dropped.
                'x-dead-letter-exchange' => [
                    'S',
                    '',
                ],
                'x-dead-letter-routing-key' => [
                    'S',
                    $queue,
                ],
            ]
        );

        $channel->queue_bind(
            $retryQueue,
            $retryExchange,
            'retry'
        );
    }
}
