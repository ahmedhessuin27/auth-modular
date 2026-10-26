<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\ConsumerLimits;
use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;
use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use UnexpectedValueException;

final class RabbitMQConsumer implements MessageConsumerInterface
{
    use PublishesWithConfirms;

    // How often an idle consumer wakes up to check stop() and its limits.
    private const IDLE_WAIT_SECONDS = 5;

    private const MAX_RECONNECT_DELAY_SECONDS = 30;

    private bool $stopping = false;

    public function __construct(
        private readonly RabbitMQConnection $rabbitMQConnection,
    ) {
    }

    public function stop(): void
    {
        $this->stopping = true;
    }

    public function consume(
        string $queue,
        callable $handler,
        ConsumerLimits $limits = new ConsumerLimits(),
    ): void {
        $this->stopping = false;
        $startedAt = microtime(true);
        $handled = 0;
        $reconnectDelay = 1;

        while (! $this->shouldStop($limits, $handled, $startedAt)) {
            $connection = null;

            try {
                $connection = $this->rabbitMQConnection->connect();
                $reconnectDelay = 1;

                $this->consumeOn(
                    $connection,
                    $queue,
                    $handler,
                    $limits,
                    $handled,
                    $startedAt,
                );
            } catch (AMQPConnectionClosedException|AMQPIOException|AMQPIOWaitException $exception) {
                // Deliveries left unacked are requeued by the broker.
                Log::warning('RabbitMQ connection failed; reconnecting', [
                    'queue' => $queue,
                    'retry_in_seconds' => $reconnectDelay,
                    'exception' => $exception->getMessage(),
                ]);

                // Sleep in short steps so stop() and the limits still apply
                // during a long backoff.
                for ($slept = 0; $slept < $reconnectDelay && ! $this->shouldStop($limits, $handled, $startedAt); $slept++) {
                    sleep(1);
                }

                $reconnectDelay = min($reconnectDelay * 2, self::MAX_RECONNECT_DELAY_SECONDS);
            } finally {
                $this->closeQuietly($connection);
            }
        }
    }

    private function consumeOn(
        AbstractConnection $connection,
        string $queue,
        callable $handler,
        ConsumerLimits $limits,
        int &$handled,
        float $startedAt,
    ): void {
        $channel = $connection->channel();

        // Bound how many unacked deliveries the broker pushes to this worker.
        $channel->basic_qos(
            0,
            config('rabbitmq.prefetch_count'),
            false
        );

        // Retries are republished on this channel and need confirms.
        $channel->confirm_select();

        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $amqpMessage) use (
                $handler,
                $channel,
                $queue,
                &$handled
            ): void {
                $this->receive(
                    $amqpMessage,
                    $channel,
                    $queue,
                    $handler,
                );

                $handled++;
            }
        );

        while ($channel->is_consuming() && ! $this->shouldStop($limits, $handled, $startedAt)) {
            try {
                $channel->wait(null, false, self::IDLE_WAIT_SECONDS);
            } catch (AMQPTimeoutException) {
                // Nothing arrived; loop to re-check stop() and the limits.
            }
        }

        // Prefetched deliveries not yet handled are requeued on close.
        $channel->close();
    }

    private function shouldStop(
        ConsumerLimits $limits,
        int $handled,
        float $startedAt,
    ): bool {
        return $this->stopping || $limits->reached($handled, $startedAt);
    }

    private function receive(
        AMQPMessage $amqpMessage,
        AMQPChannel $channel,
        string $queue,
        callable $handler,
    ): void {
        try {
            $message = $this->decode($amqpMessage);
        } catch (\Throwable $exception) {
            Log::error('Discarding malformed message to the dead-letter queue', [
                'queue' => $queue,
                'message_id' => $amqpMessage->has('message_id')
                    ? $amqpMessage->get('message_id')
                    : null,
                'exception' => $exception->getMessage(),
            ]);

            // Retrying can't fix a bad payload; send it to the DLQ.
            $amqpMessage->nack(requeue: false);

            return;
        }

        $this->process(
            $amqpMessage,
            $message,
            $channel,
            $queue,
            $handler,
        );
    }

    private function decode(AMQPMessage $amqpMessage): Message
    {
        $data = json_decode(
            $amqpMessage->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($data) || ! is_string($data['name'] ?? null) || $data['name'] === '') {
            throw new UnexpectedValueException(
                'Message body is missing a "name"'
            );
        }

        return new Message(
            name: $data['name'],
            payload: $data['payload'] ?? [],
            headers: $data['headers'] ?? [],
            messageId: $data['message_id'] ?? null,
            correlationId: $data['correlation_id'] ?? null,
        );
    }

    private function process(
        AMQPMessage $amqpMessage,
        Message $message,
        AMQPChannel $channel,
        string $queue,
        callable $handler,
    ): void {
        try {
            // Deduplicate per queue: the same event fans out to every
            // subscribing module with the same message id.
            $alreadyProcessed = DB::table('processed_messages')
                ->where('queue', $queue)
                ->where('message_id', $message->messageId)
                ->exists();

            if (! $alreadyProcessed) {
                $handler($message);

                DB::table('processed_messages')->insertOrIgnore([
                    'queue' => $queue,
                    'message_id' => $message->messageId,
                    'message_name' => $message->name,
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $exception) {
            $this->retryOrDeadLetter(
                $amqpMessage,
                $message,
                $channel,
                $queue,
                $exception,
            );

            return;
        }

        // Outside the try: a broken connection here has to reach consume()
        // and reconnect, not be mistaken for a handler failure.
        $amqpMessage->ack();
    }

    private function retryOrDeadLetter(
        AMQPMessage $amqpMessage,
        Message $message,
        AMQPChannel $channel,
        string $queue,
        \Throwable $exception,
    ): void {
        $retryCount = (int) ($message->headers['retry_count'] ?? 0);

        Log::warning('Message processing failed', [
            'message_id' => $message->messageId,
            'message_name' => $message->name,
            'retry_count' => $retryCount,
            'exception' => $exception->getMessage(),
        ]);

        if ($retryCount < config('rabbitmq.retry.max_retries')) {
            try {
                // Publish the retry and wait for RabbitMQ's confirmation.
                $this->publishToRetryQueue(
                    $channel,
                    $queue,
                    $message,
                    $retryCount + 1,
                );
            } catch (\Throwable $retryException) {
                Log::error('Failed to publish retry message; requeuing original', [
                    'message_id' => $message->messageId,
                    'queue' => $queue,
                    'exception' => $retryException->getMessage(),
                ]);

                // Preserve the original message if retry publishing fails.
                // php-amqplib's signature is nack($requeue, $multiple).
                $amqpMessage->nack(requeue: true);

                return;
            }

            // Acknowledge the original only after confirmation.
            $amqpMessage->ack();

            return;
        }

        Log::error('Message exhausted its retry attempts', [
            'message_id' => $message->messageId,
            'message_name' => $message->name,
            'queue' => $queue,
        ]);

        // Reject without requeueing; RabbitMQ's DLX routes it to the DLQ.
        $amqpMessage->nack(requeue: false);
    }

    private function publishToRetryQueue(
        AMQPChannel $channel,
        string $queue,
        Message $message,
        int $retryCount,
    ): void {
        $retryExchange = $queue . '.retry.exchange';

        $retryMessage = $this->toAmqpMessage(new Message(
            name: $message->name,
            payload: $message->payload,
            headers: array_merge(
                $message->headers,
                ['retry_count' => $retryCount],
            ),
            messageId: $message->messageId,
            correlationId: $message->correlationId,
        ));

        $routed = $this->publishWithConfirm(
            $channel,
            $retryMessage,
            $retryExchange,
            'retry',
        );

        if (! $routed) {
            throw new RuntimeException(
                "Retry message could not be routed to a queue: {$retryExchange}"
            );
        }
    }

    private function closeQuietly(?AbstractConnection $connection): void
    {
        try {
            $connection?->close();
        } catch (\Throwable) {
            // Already broken; the broker requeues its unacked deliveries.
        }
    }
}
