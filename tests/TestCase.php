<?php

namespace Tests;

use App\Modules\Auth\IntegrationEvents\VerificationCodeIssued;
use App\Modules\Shared\Application\Messaging\MessageDispatcher;
use App\Modules\Shared\Application\Messaging\MessageSubscriptions;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxMessage;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stands in for the outbox relay and RabbitMQ: hands each pending
     * outbox message to every module queue subscribed to it, the way
     * `rabbitmq:consume` would.
     */
    protected function deliverOutboxMessages(): void
    {
        $subscriptions = app(MessageSubscriptions::class);
        $dispatcher = app(MessageDispatcher::class);

        foreach (OutboxMessage::query()->pending()->orderBy('id')->get() as $outboxMessage) {
            foreach ($subscriptions->queues() as $queue) {
                if ($subscriptions->handlerFor($queue, $outboxMessage->name) !== null) {
                    $dispatcher->dispatch($queue, $outboxMessage->toMessage());
                }
            }

            $outboxMessage->forceFill(['published_at' => now()])->save();
        }
    }

    /**
     * The last verification code Auth asked to have emailed.
     */
    protected function lastVerificationCode(): string
    {
        return OutboxMessage::query()
            ->where('name', VerificationCodeIssued::NAME)
            ->latest('id')
            ->firstOrFail()
            ->payload['code'];
    }
}
