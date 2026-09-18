<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Handlers\NotificationEventHandler;
use App\Modules\Notifications\Application\Handlers\UserRegisteredHandler;
use App\Modules\Shared\Application\Messaging\Message;

final class NotificationEventDispatcher
{
    public function __construct(
        private readonly UserRegisteredHandler $userRegisteredHandler,
    ) {
    }

    public function dispatch(Message $message): void
    {
        $handlers = [
            'auth.user.registered' => $this->userRegisteredHandler,
        ];

        $handler = $handlers[$message->name] ?? null;

        if ($handler === null) {
            return;
        }

        $handler->handle($message);
    }
}