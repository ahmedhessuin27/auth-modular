<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumer;
use App\Modules\Shared\Application\Messaging\Contracts\MessageConsumerInterface;
use App\Modules\Shared\Application\Messaging\Message;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQConsumer implements MessageConsumerInterface
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly RabbitMQConnection $rabbitMQConnection,
    ) {
    }

    public function consume(string $queue, callable $handler): void
    {
        $connection = $this->rabbitMQConnection->connect();

        $channel = $connection->channel();

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
                $queue
            ): void {

                $data = json_decode(
                    $amqpMessage->getBody(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $message = new Message(
                    name: $data['name'],
                    payload: $data['payload'] ?? [],
                    headers: $data['headers'] ?? [],
                    messageId: $data['message_id'] ?? null,
                    correlationId: $data['correlation_id'] ?? null,
                );

                try {
                    $handler($message);

                    $amqpMessage->ack();

                } catch (\Throwable $exception) {

                    $retryCount = (int) (
                        $message->headers['retry_count'] ?? 0
                    );

                    if ($retryCount < self::MAX_RETRIES) {

                        $nextRetryCount = $retryCount + 1;

                        $this->publishToRetryQueue(
                            $channel,
                            $queue,
                            $message,
                            $nextRetryCount
                        );

                        $amqpMessage->ack();

                        return;
                    }

                    $amqpMessage->nack(
                        false,
                        false
                    );
                }
            }
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function publishToRetryQueue(
        $channel,
        string $queue,
        Message $message,
        int $retryCount
    ): void {
        $retryExchange = $queue . '.retry.exchange';

        $retryMessage = new AMQPMessage(
            json_encode([
                'message_id' => $message->messageId,
                'name' => $message->name,
                'payload' => $message->payload,
                'headers' => array_merge(
                    $message->headers,
                    [
                        'retry_count' => $retryCount,
                    ]
                ),
                'correlation_id' => $message->correlationId,
            ], JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $message->messageId,
                'type' => $message->name,
            ]
        );

        $channel->basic_publish(
            $retryMessage,
            $retryExchange,
            'retry'
        );
    }
}