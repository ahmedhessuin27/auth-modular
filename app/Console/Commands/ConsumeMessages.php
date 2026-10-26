<?php

namespace App\Console\Commands;

use App\Modules\Shared\Application\Messaging\ConsumerLimits;
use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;
use App\Modules\Shared\Application\Messaging\Message;
use App\Modules\Shared\Application\Messaging\MessageDispatcher;
use App\Modules\Shared\Application\Messaging\MessageSubscriptions;
use Illuminate\Console\Command;

class ConsumeMessages extends Command
{
    protected $signature = 'rabbitmq:consume
                            {queue : Queue to consume, as subscribed by a module}
                            {--max-messages= : Exit after handling this many messages}
                            {--max-time= : Exit after this many seconds}
                            {--memory=128 : Exit once memory use exceeds this many MB}';

    protected $description = 'Consume a module queue and dispatch each message to its handler';

    public function handle(
        MessageConsumerInterface $consumer,
        MessageSubscriptions $subscriptions,
        MessageDispatcher $dispatcher,
    ): int {
        $queue = $this->argument('queue');

        if (! in_array($queue, $subscriptions->queues(), true)) {
            $this->error("No module subscribes to [{$queue}].");
            $this->line('Subscribed queues: ' . implode(', ', $subscriptions->queues()));

            return self::FAILURE;
        }

        if (extension_loaded('pcntl')) {
            // Finish the in-flight message, then exit cleanly.
            $this->trap([SIGTERM, SIGINT], fn () => $consumer->stop());
        }

        $this->info("Consuming [{$queue}]...");

        $consumer->consume(
            $queue,
            fn (Message $message) => $dispatcher->dispatch($queue, $message),
            new ConsumerLimits(
                maxMessages: $this->intOption('max-messages'),
                maxSeconds: $this->intOption('max-time'),
                maxMemoryMb: $this->intOption('memory'),
            ),
        );

        $this->info('Consumer stopped.');

        return self::SUCCESS;
    }

    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null ? null : (int) $value;
    }
}
