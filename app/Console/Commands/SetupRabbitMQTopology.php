<?php

namespace App\Console\Commands;

use App\Modules\Shared\Application\Messaging\Contracts\MessageTopologyInterface;
use App\Modules\Shared\Application\Messaging\MessageSubscriptions;
use Illuminate\Console\Command;

class SetupRabbitMQTopology extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description = 'Set up RabbitMQ exchanges, queues, and bindings';

    public function handle(
        MessageTopologyInterface $topology,
        MessageSubscriptions $subscriptions,
    ): int {
        try {
            // Each module declares its own queue and bindings when it subscribes.
            $topologies = $subscriptions->topologies();

            $topology->setup(...$topologies);

            foreach ($topologies as $queueTopology) {
                $this->line(
                    "  {$queueTopology->queue} <- " . implode(', ', $queueTopology->bindings)
                );
            }

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
