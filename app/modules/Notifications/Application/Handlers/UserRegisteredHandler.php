<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Auth\IntegrationEvents\UserRegistered;
use App\Modules\Notifications\Application\Notifications\WelcomeNotification;
use App\Modules\Shared\Application\Messaging\Contracts\MessageHandlerInterface;
use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class UserRegisteredHandler implements MessageHandlerInterface
{
    public function handle(Message $message): void
    {
        $event = UserRegistered::fromPayload($message->payload);

        // On-demand: the event carries everything the email needs, so this
        // module never reads the Users module's data.
        Notification::route('mail', [$event->email => $event->name])
            ->notify(new WelcomeNotification($event->name));

        Log::info('Sent welcome notification', [
            'message_id' => $message->messageId,
            'user_id' => $event->userId,
        ]);
    }
}
