<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Auth\IntegrationEvents\VerificationCodeIssued;
use App\Modules\Notifications\Application\Notifications\VerificationCodeNotification;
use App\Modules\Shared\Application\Messaging\Contracts\MessageHandlerInterface;
use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\Notification;

final class VerificationCodeIssuedHandler implements MessageHandlerInterface
{
    public function handle(Message $message): void
    {
        $event = VerificationCodeIssued::fromPayload($message->payload);

        // No user may exist yet (sign-up), so this always sends on demand.
        Notification::route('mail', $event->email)
            ->notify(new VerificationCodeNotification(
                code: $event->code,
                purpose: $event->purpose,
                expiresInMinutes: $event->expiresInMinutes,
            ));
    }
}
