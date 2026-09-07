<?php

namespace App\Modules\Shared\Application\Messaging\Contracts;

use App\Modules\Shared\Application\Messaging\Message;

/**
 * Port for the long-running worker side. The RabbitMQ adapter pulls frames
 * off a queue, rebuilds a Message, and invokes the registered handler;
 * returning normally acks, throwing nacks (and lets the DLX take over).
 */
interface MessageConsumerInterface
{
    /**
     * @param  non-empty-string  $queue
     * @param  callable(Message): void  $handler
     */
    public function consume(string $queue, callable $handler): void;
}
