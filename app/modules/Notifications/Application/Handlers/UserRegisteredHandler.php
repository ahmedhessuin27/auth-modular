<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Auth\IntegrationEvents\UserRegistered;
use App\Modules\Shared\Application\Messaging\Contracts\MessageHandlerInterface;
use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\Log;

final class UserRegisteredHandler implements MessageHandlerInterface
{
    public function handle(Message $message): void
    {
        $event = UserRegistered::fromPayload($message->payload);

        Log::info('Handling user registered event', [
            'message_id' => $message->messageId,
            'user_id' => $event->userId,
            'email' => $event->email,
            'name' => $event->name,
        ]);
    }
}
