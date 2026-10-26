<?php

namespace App\Modules\Shared\Application\Messaging\Contracts;

use App\Modules\Shared\Application\Messaging\ConsumerLimits;
use App\Modules\Shared\Application\Messaging\Message;

/**
 * Port for the long-running worker side. The RabbitMQ adapter pulls frames
 * off a queue, rebuilds a Message, and invokes the registered handler;
 * returning normally acks, throwing nacks (and lets the DLX take over).
 */
interface MessageConsumerInterface
{
    /**
     * Blocks until stop() is called or one of the limits is reached,
     * reconnecting on its own if the broker connection drops.
     *
     * @param  non-empty-string  $queue
     * @param  callable(Message): void  $handler
     */
    public function consume(
        string $queue,
        callable $handler,
        ConsumerLimits $limits = new ConsumerLimits(),
    ): void;

    /**
     * Asks a running consume() to return once the in-flight message is
     * finished. Safe to call from a signal handler.
     */
    public function stop(): void;
}
