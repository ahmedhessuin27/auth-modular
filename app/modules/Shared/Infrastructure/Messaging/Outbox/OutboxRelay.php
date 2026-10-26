<?php

namespace App\Modules\Shared\Infrastructure\Messaging\Outbox;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ships pending outbox rows to the broker in insertion order.
 *
 * Delivery is at-least-once: a crash between publishing a row and marking
 * it sent re-sends that row, which consumers absorb via processed_messages.
 * Run a single relay process; a second one would only add duplicates.
 */
final class OutboxRelay
{
    public function __construct(
        private readonly MessagePublisherInterface $publisher,
    ) {
    }

    /**
     * @return int number of messages published
     */
    public function relay(int $batchSize): int
    {
        $outboxMessages = OutboxMessage::query()
            ->pending()
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $published = 0;

        foreach ($outboxMessages as $outboxMessage) {
            try {
                $this->publisher->publish($outboxMessage->toMessage());
            } catch (\Throwable $exception) {
                $outboxMessage->increment('attempts', 1, [
                    'last_error' => Str::limit($exception->getMessage(), 1000),
                ]);

                Log::warning('Outbox relay failed to publish message', [
                    'message_id' => $outboxMessage->message_id,
                    'message_name' => $outboxMessage->name,
                    'attempts' => $outboxMessage->attempts,
                    'exception' => $exception->getMessage(),
                ]);

                // Stop so later messages can't overtake this one; the next
                // run retries it first.
                break;
            }

            $outboxMessage->increment('attempts', 1, [
                'published_at' => now(),
                'last_error' => null,
            ]);

            $published++;
        }

        return $published;
    }
}
