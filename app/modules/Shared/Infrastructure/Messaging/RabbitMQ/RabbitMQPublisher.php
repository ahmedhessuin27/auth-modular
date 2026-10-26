<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPIOWaitException;

/**
 * Publishes straight to the broker and waits for its confirmation. Only the
 * outbox relay should use this; application code publishes via the outbox.
 */
final class RabbitMQPublisher implements MessagePublisherInterface
{
    use PublishesWithConfirms;

    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly RabbitMQConnection $rabbitMQConnection,
    ) {
    }

    public function publish(Message $message): void
    {
        try {
            $routed = $this->attempt($message);
        } catch (AMQPConnectionClosedException|AMQPIOException|AMQPIOWaitException) {
            // The broker can drop a connection that sat idle between
            // publishes. Retry once on a fresh one; if the first attempt
            // did land, consumers dedupe the copy by message id.
            $routed = $this->attempt($message);
        }

        if (! $routed) {
            // Accepted by the broker, but no queue binds this routing key.
            Log::warning('Published message has no subscribers', [
                'message_id' => $message->messageId,
                'message_name' => $message->name,
            ]);
        }
    }

    private function attempt(Message $message): bool
    {
        try {
            return $this->publishWithConfirm(
                $this->channel(),
                $this->toAmqpMessage($message),
                config('rabbitmq.exchange'),
                $message->name,
            );
        } catch (\Throwable $exception) {
            // The channel may be unusable now; reconnect on the next attempt.
            $this->disconnect();

            throw $exception;
        }
    }

    /**
     * Opened once and reused, so a burst of publishes doesn't pay a
     * TCP + TLS handshake each time.
     */
    private function channel(): AMQPChannel
    {
        if ($this->channel?->is_open()) {
            try {
                // Sends a heartbeat if one is due, and throws if the broker
                // has gone quiet, so a stale connection is replaced before
                // anything is published on it.
                $this->connection->checkHeartBeat();

                return $this->channel;
            } catch (AMQPConnectionClosedException) {
                // Fall through and reconnect.
            }
        }

        $this->disconnect();

        $this->connection = $this->rabbitMQConnection->connect();
        $this->channel = $this->connection->channel();
        $this->channel->confirm_select();

        $this->channel->exchange_declare(
            config('rabbitmq.exchange'),
            'topic',
            false,
            true,
            false
        );

        return $this->channel;
    }

    private function disconnect(): void
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (\Throwable) {
            // Already broken; there is nothing left to close cleanly.
        } finally {
            $this->channel = null;
            $this->connection = null;
        }
    }
}
