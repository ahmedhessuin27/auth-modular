<?php

namespace App\Console\Commands;

use App\Modules\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQTopology;
use Illuminate\Console\Command;

class SetupRabbitMQTopology extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description = 'Set up RabbitMQ exchanges, queues, and bindings';

    public function handle(
        RabbitMQTopology $topology
    ): int {
        try {
            $topology->setup();

            $this->info('RabbitMQ topology created successfully!');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Failed to setup RabbitMQ topology.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}