<?php

namespace App\Console\Commands;

use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxRelay;
use Illuminate\Console\Command;

class RelayOutboxMessages extends Command
{
    protected $signature = 'rabbitmq:relay-outbox
                            {--once : Relay a single batch and exit}
                            {--batch=100 : Messages to publish per batch}
                            {--sleep=1 : Seconds to wait when there is nothing to publish}';

    protected $description = 'Publish pending outbox messages to RabbitMQ';

    public function handle(
        OutboxRelay $relay
    ): int {
        $running = true;

        if (extension_loaded('pcntl')) {
            // Finish the current batch, then exit cleanly.
            $this->trap([SIGTERM, SIGINT], function () use (&$running): void {
                $running = false;
            });
        }

        $batchSize = (int) $this->option('batch');

        $this->info('Relaying outbox messages...');

        do {
            $published = $relay->relay($batchSize);

            if ($published > 0) {
                $this->line("Published {$published} message(s).");
            }

            // A full batch means more may be waiting, so go again immediately.
            if ($running && ! $this->option('once') && $published < $batchSize) {
                sleep((int) $this->option('sleep'));
            }
        } while ($running && ! $this->option('once'));

        return self::SUCCESS;
    }
}
