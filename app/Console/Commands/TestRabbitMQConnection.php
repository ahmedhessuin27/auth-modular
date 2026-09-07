<?php

namespace App\Console\Commands;

use App\Modules\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQConnection;
use Illuminate\Console\Command;

class TestRabbitMQConnection extends Command
{
    protected $signature = 'rabbitmq:test';

    protected $description = 'Test the connection to RabbitMQ';

    public function handle(RabbitMQConnection $rabbitMQConnection): int
    {
        try {
            $connection = $rabbitMQConnection->connect();

            if ($connection->isConnected()) {
                $this->info('Successfully connected to CloudAMQP!');

                $connection->close();

                return self::SUCCESS;
            }

            $this->error('Failed to connect to CloudAMQP.');

            return self::FAILURE;

        } catch (\Throwable $exception) {
            $this->error('RabbitMQ connection failed:');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}