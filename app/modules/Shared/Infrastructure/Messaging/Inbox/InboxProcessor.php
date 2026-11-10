<?php

namespace App\Modules\Shared\Infrastructure\Messaging\Inbox;

use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\DB;

/**
 * Runs a consumed message's handler at most once per queue.
 *
 * The processed_messages row is claimed first and the handler runs in the
 * same transaction, so:
 *  - a second worker holding a duplicate blocks on the (queue, message_id)
 *    unique key until the first commits, then skips it (or claims it, if
 *    the first rolled back);
 *  - database writes the handler makes, outbox events included, commit or
 *    roll back together with the claim, so a failed attempt leaves nothing
 *    behind and its retry starts clean.
 *
 * Effects outside the database, such as sending mail, can still repeat if
 * the process dies after the handler returns but before the commit.
 */
final class InboxProcessor
{
    /**
     * @param  callable(Message): void  $handler
     * @return bool false when this queue had already processed the message
     */
    public function process(string $queue, Message $message, callable $handler): bool
    {
        return DB::transaction(function () use ($queue, $message, $handler): bool {
            $claimed = ProcessedMessage::query()->insertOrIgnore([
                'queue' => $queue,
                'message_id' => $message->messageId,
                'message_name' => $message->name,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]) > 0;

            if (! $claimed) {
                return false;
            }

            $handler($message);

            return true;
        });
    }
}
