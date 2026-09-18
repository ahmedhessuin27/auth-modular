<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Shared\Application\Messaging\Message;

interface NotificationEventHandler
{
    public function handle(Message $message): void;
}