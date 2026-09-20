<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\Log;

final class UserRegisteredHandler implements NotificationEventHandler
{
    public function handle(Message $message): void
    {
        Log::info('Handling user registered event', [
            'user_id' => $message->payload['user_id'],
            'email' => $message->payload['email'],
            'name' => $message->payload['name'],
        ]);
    }
}