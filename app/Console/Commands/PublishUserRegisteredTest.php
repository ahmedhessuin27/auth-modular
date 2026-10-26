<?php

namespace App\Console\Commands;

use App\Modules\Auth\Application\Services\AuthEventPublisher;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use Illuminate\Console\Command;

class PublishUserRegisteredTest extends Command
{
    protected $signature = 'rabbitmq:publish-user-registered';

    protected $description = 'Publish a test user registered event';

    public function handle(
        AuthEventPublisher $eventPublisher
    ): int {
        try {
            $event = new UserRegistered(
                userId: '123',
                email: 'test@example.com',
                name: 'Ahmed',
            );

            $eventPublisher->userRegistered($event);

            $this->info(
                'User registered event stored in the outbox; run rabbitmq:relay-outbox to deliver it.'
            );

            return self::SUCCESS;

        } catch (\Throwable $exception) {

            $this->error(
                'Failed to publish user registered event.'
            );

            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}