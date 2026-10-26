<?php

namespace App\Modules\Shared\Application\Messaging\Contracts;

use App\Modules\Shared\Application\Messaging\Message;

/**
 * Port for emitting integration events / commands to other modules or
 * services. Application code depends on this — never on php-amqplib.
 *
 * The bound implementation stores messages in the transactional outbox:
 * call it inside the same DB transaction as the state change it announces,
 * and `rabbitmq:relay-outbox` delivers it once that transaction commits.
 */
interface MessagePublisherInterface
{
    public function publish(Message $message): void;
}
