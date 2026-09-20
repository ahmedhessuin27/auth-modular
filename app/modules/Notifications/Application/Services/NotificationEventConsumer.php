<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;

final class NotificationEventConsumer
{
    public function __construct(
        private readonly MessageConsumerInterface $consumer,
        private readonly NotificationEventDispatcher $dispatcher,
    ) {
    }

    public function consume(): void
    {
        $this->consumer->consume(
            'notifications.events.queue',
            function ($message): void {
                $this->dispatcher->dispatch($message);
            }
        );
    }
}