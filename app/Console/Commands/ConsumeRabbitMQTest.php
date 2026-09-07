<?php

namespace App\Console\Commands;

use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;
use Illuminate\Console\Command;

class ConsumeRabbitMQTest extends Command
{
    protected $signature = 'rabbitmq:consume-test';

    protected $description = 'Consume test messages from RabbitMQ';

    public function handle(
        MessageConsumerInterface $consumer
    ): int {
        $this->info('Waiting for RabbitMQ messages...');

        $consumer->consume(
            'test.queue',
            function ($message): void {
                $this->info('Hello from RabbitMQ!');

                $this->line(
                    'Message: ' . json_encode($message->payload)
                );
            }
        );

        return self::SUCCESS;
    }
}