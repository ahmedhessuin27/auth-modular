<?php

namespace App\Console\Commands;

use App\Modules\Shared\Application\Messaging\Contracts\MessageTopologyInterface;
use App\Modules\Shared\Application\Messaging\QueueTopology;
use Illuminate\Console\Command;

class SetupRabbitMQTopology extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description = 'Set up RabbitMQ exchanges, queues, and bindings';

    public function handle(
        MessageTopologyInterface $topology
    ): int {
        try {
            $topology->setup(
                new QueueTopology(
                    queue: 'notifications.events.queue',
                    bindings: [
                        'auth.user.registered',
                    ],
                ),
            );

            $this->info(
                'RabbitMQ topology created successfully!'
            );

            return self::SUCCESS;

        } catch (\Throwable $exception) {

            $this->error(
                'Failed to setup RabbitMQ topology.'
            );

            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}