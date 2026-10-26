<?php

namespace App\Modules\Shared\Infrastructure\Messaging\Outbox;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\Message;

/**
 * Default MessagePublisherInterface binding. Writes the message to the
 * outbox on the app's DB connection, so publishing inside the transaction
 * that changes state makes the change and its event commit together.
 * OutboxRelay delivers the stored rows to the broker afterwards.
 */
final class OutboxPublisher implements MessagePublisherInterface
{
    public function publish(Message $message): void
    {
        OutboxMessage::fromMessage($message)->save();
    }
}
