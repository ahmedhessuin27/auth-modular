<?php

namespace App\Console\Commands;

use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;
use Illuminate\Console\Command;

class ConsumeRabbitMQFailureTest extends Command
{
    protected $signature = 'rabbitmq:consume-failure-test';

    protected $description = 'Test RabbitMQ dead-letter handling';

    public function handle(
        MessageConsumerInterface $consumer
    ): int {
        $this->info('Waiting for RabbitMQ messages...');

        $consumer->consume(
            'test.queue',
            function ($message): void {
                $this->error('Processing message...');

                throw new \RuntimeException(
                    'Intentional test failure'
                );
            }
        );

        return self::SUCCESS;
    }
}