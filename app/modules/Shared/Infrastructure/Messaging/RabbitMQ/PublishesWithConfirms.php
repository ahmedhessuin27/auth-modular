<?php

namespace App\Modules\Shared\Infrastructure\Messaging\RabbitMQ;

use App\Modules\Shared\Application\Messaging\Message;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

/**
 * Wire format and publisher-confirm handling shared by everything that
 * publishes to RabbitMQ. The channel must already be in confirm mode.
 */
trait PublishesWithConfirms
{
    private function toAmqpMessage(Message $message): AMQPMessage
    {
        return new AMQPMessage(
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
            ],
        );
    }

    /**
     * Publishes as mandatory and blocks until RabbitMQ confirms it.
     *
     * @return bool false when the broker accepted the message but no queue was bound to receive it
     *
     * @throws RuntimeException when the broker rejects the message
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException when no confirmation arrives in time
     */
    private function publishWithConfirm(
        AMQPChannel $channel,
        AMQPMessage $amqpMessage,
        string $exchange,
        string $routingKey,
    ): bool {
        $returned = false;
        $nacked = false;

        // Detect messages that RabbitMQ cannot route to a queue.
        $channel->set_return_listener(
            function (
                $replyCode,
                $replyText,
                $exchange,
                $routingKey,
                AMQPMessage $returnedMessage,
            ) use (&$returned): void {
                $returned = true;
            }
        );

        // Without a handler, php-amqplib silently swallows broker nacks.
        $channel->set_nack_handler(
            function (AMQPMessage $nackedMessage) use (&$nacked): void {
                $nacked = true;
            }
        );

        // The fourth argument enables mandatory publishing.
        $channel->basic_publish(
            $amqpMessage,
            $exchange,
            $routingKey,
            true,
        );

        // Process publisher confirmations and returned messages.
        $channel->wait_for_pending_acks_returns(
            config('rabbitmq.confirm_timeout')
        );

        if ($nacked) {
            throw new RuntimeException(
                "RabbitMQ rejected the message published to: {$exchange}"
            );
        }

        return ! $returned;
    }
}
