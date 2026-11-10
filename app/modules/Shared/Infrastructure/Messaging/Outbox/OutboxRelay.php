<?php

namespace App\Modules\Shared\Infrastructure\Messaging\Outbox;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ships pending outbox rows to the broker in insertion order.
 *
 * Delivery is at-least-once: a crash between publishing a row and marking
 * it sent re-sends that row, which consumers absorb via processed_messages.
 *
 * Each batch runs under a cache lock, so extra relay processes (e.g. an
 * overlapping deploy) wait their turn instead of sending duplicates out of
 * order. The lock only spans processes when the cache store is shared
 * between them (database or redis, not file or array).
 */
final class OutboxRelay
{
    private const LOCK_NAME = 'messaging:outbox-relay';

    private const LOCK_SECONDS = 120;

    /**
     * A batch stops starting new publishes after this long, so the lock
     * can't expire while a publish (bounded by confirm_timeout) is running.
     */
    private const MAX_BATCH_SECONDS = self::LOCK_SECONDS / 2;

    public function __construct(
        private readonly MessagePublisherInterface $publisher,
    ) {}

    /**
     * @return int number of messages published; 0 while another relay holds the lock
     */
    public function relay(int $batchSize): int
    {
        $published = Cache::lock(self::LOCK_NAME, self::LOCK_SECONDS)
            ->get(fn (): int => $this->relayBatch($batchSize));

        return $published === false ? 0 : $published;
    }

    private function relayBatch(int $batchSize): int
    {
        $startedAt = microtime(true);

        $outboxMessages = OutboxMessage::query()
            ->pending()
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $published = 0;

        foreach ($outboxMessages as $outboxMessage) {
            if (microtime(true) - $startedAt >= self::MAX_BATCH_SECONDS) {
                break;
            }

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
