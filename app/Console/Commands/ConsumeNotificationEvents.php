<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Application\Services\NotificationEventConsumer;
use Illuminate\Console\Command;

class ConsumeNotificationEvents extends Command
{
    protected $signature = 'rabbitmq:consume-notifications';

    protected $description = 'Consume notification events from RabbitMQ';

    public function handle(
        NotificationEventConsumer $consumer
    ): int {
        $this->info('Waiting for notification events...');

        $consumer->consume();

        return self::SUCCESS;
    }
}