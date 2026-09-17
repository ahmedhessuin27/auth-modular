<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\Contracts\MessageTopologyInterface;
use App\Modules\Shared\Application\Messaging\QueueTopology;

final class RabbitMQTopology implements MessageTopologyInterface
{
    private const EVENTS_EXCHANGE = 'modular.events';

    public function setup(QueueTopology ...$topologies): void
    {
        $connection = app(RabbitMQConnection::class)->connect();

        $channel = $connection->channel();

        /*
        |--------------------------------------------------------------------------
        | Main Events Exchange
        |--------------------------------------------------------------------------
        */

        $channel->exchange_declare(
            self::EVENTS_EXCHANGE,
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
        $channel,
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
                self::EVENTS_EXCHANGE,
                $routingKey
            );
        }
    }

    private function declareDeadLetterTopology(
        $channel,
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
        $channel,
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
        $channel,
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
                    5000,
                ],
                'x-dead-letter-exchange' => [
                    'S',
                    self::EVENTS_EXCHANGE,
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