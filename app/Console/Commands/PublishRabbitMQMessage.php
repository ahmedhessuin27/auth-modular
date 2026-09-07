<?php

namespace App\Console\Commands;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Console\Command;

class PublishRabbitMQMessage extends Command
{
    protected $signature = 'rabbitmq:publish-test';

    protected $description = 'Publish a test message to RabbitMQ';

    public function handle(
        MessagePublisherInterface $publisher
    ): int {
        try {
            $message = new Message(
                name: 'test.message',
                payload: [
                    'message' => 'Hello from Laravel',
                    'published_at' => now()->toDateTimeString(),
                ],
            );

            $publisher->publish($message);

            $this->info('Test message published successfully!');

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            $this->error('Failed to publish message:');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}