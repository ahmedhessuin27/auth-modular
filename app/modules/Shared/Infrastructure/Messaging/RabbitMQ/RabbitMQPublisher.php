<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\Message;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQPublisher implements MessagePublisherInterface
{
    public function __construct(
        private readonly RabbitMQConnection $connection,
    ) {
    }

    public function publish(Message $message): void
    {
        $connection = $this->connection->connect();

        $channel = $connection->channel();

        $channel->exchange_declare(
            'modular.events',
            'topic',
            false,
            true,
            false
        );

        $amqpMessage = new AMQPMessage(
            json_encode([
                'message_id' => $message->messageId,
                'name' => $message->name,
                'payload' => $message->payload,
                'headers' => $message->headers,
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
            $amqpMessage,
            'modular.events',
            $message->name
        );

        $channel->close();
        $connection->close();
    }
}