<?php

namespace App\Modules\Shared\Application\Messaging\Contracts;

use App\Modules\Shared\Application\Messaging\Message;

/**
 * Port for emitting integration events / commands to other modules or
 * services. Application code depends on this — never on php-amqplib.
 */
interface MessagePublisherInterface
{
    public function publish(Message $message): void;
}
