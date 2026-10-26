<?php

namespace App\Modules\Shared\Application\Messaging;

use App\Modules\Shared\Application\Messaging\Contracts\MessageHandlerInterface;
use LogicException;

/**
 * Which queue each module consumes, and which handler runs for each message
 * name on it. Modules subscribe from their own service provider; topology
 * setup and the consumer both read from here, so a subscription is declared
 * in exactly one place.
 */
final class MessageSubscriptions
{
    /**
     * @var array<string, array<string, class-string<MessageHandlerInterface>>>
     */
    private array $handlers = [];

    /**
     * @param  non-empty-string  $queue
     * @param  array<non-empty-string, class-string<MessageHandlerInterface>>  $handlers  message name => handler class
     */
    public function subscribe(string $queue, array $handlers): void
    {
        foreach ($handlers as $messageName => $handler) {
            $existing = $this->handlers[$queue][$messageName] ?? $handler;

            if ($existing !== $handler) {
                throw new LogicException(
                    "[{$messageName}] on [{$queue}] is already handled by {$existing}"
                );
            }

            $this->handlers[$queue][$messageName] = $handler;
        }
    }

    /**
     * @return list<string>
     */
    public function queues(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * @return class-string<MessageHandlerInterface>|null
     */
    public function handlerFor(string $queue, string $messageName): ?string
    {
        return $this->handlers[$queue][$messageName] ?? null;
    }

    /**
     * Each queue is bound to exactly the message names it has handlers for.
     *
     * @return list<QueueTopology>
     */
    public function topologies(): array
    {
        return array_map(
            fn (string $queue): QueueTopology => new QueueTopology(
                queue: $queue,
                bindings: array_map('strval', array_keys($this->handlers[$queue])),
            ),
            $this->queues(),
        );
    }
}
