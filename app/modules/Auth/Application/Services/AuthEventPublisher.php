<?php

namespace App\Modules\Auth\Application\Services;
use App\Modules\Auth\Domain\Events\UserRegistered;
use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\Message;

final class AuthEventPublisher
{
    public function __construct(
        private readonly MessagePublisherInterface $publisher,
    ) {
    }

    public function userRegistered(
        UserRegistered $event
    ): void {
        $message = new Message(
            name: UserRegistered::NAME,
            payload: $event->toPayload(),
        );

        $this->publisher->publish($message);
    }
}