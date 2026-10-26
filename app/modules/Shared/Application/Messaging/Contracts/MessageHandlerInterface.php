<?php

namespace App\Modules\Shared\Application\Messaging\Contracts;

use App\Modules\Shared\Application\Messaging\Message;

/**
 * Handles one message name on behalf of a module. Returning normally acks
 * the message; throwing sends it through the retry / dead-letter path.
 */
interface MessageHandlerInterface
{
    public function handle(Message $message): void;
}
