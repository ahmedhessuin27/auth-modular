<?php

namespace App\Modules\Shared\Application\Messaging;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Routes a consumed message to the handler its module subscribed for it.
 */
final class MessageDispatcher
{
    public function __construct(
        private readonly MessageSubscriptions $subscriptions,
        private readonly Container $container,
    ) {
    }

    public function dispatch(string $queue, Message $message): void
    {
        $handler = $this->subscriptions->handlerFor($queue, $message->name);

        if ($handler === null) {
            // Usually a binding left on the broker after its handler was
            // removed: rabbitmq:setup adds bindings but never deletes them.
            Log::warning('No handler subscribed for message; acknowledging it unhandled', [
                'queue' => $queue,
                'message_id' => $message->messageId,
                'message_name' => $message->name,
            ]);

            return;
        }

        $this->container->make($handler)->handle($message);
    }
}
