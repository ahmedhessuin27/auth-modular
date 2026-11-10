<?php

namespace Tests\Feature;

use App\Modules\Shared\Application\Messaging\Message;
use App\Modules\Shared\Infrastructure\Messaging\Inbox\InboxProcessor;
use App\Modules\Shared\Infrastructure\Messaging\Inbox\ProcessedMessage;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InboxProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_redelivered_message_is_handled_only_once_per_queue(): void
    {
        $inbox = app(InboxProcessor::class);
        $message = new Message(name: 'user.registered');
        $handledOn = [];

        $handler = function (string $queue) use (&$handledOn): callable {
            return function () use ($queue, &$handledOn): void {
                $handledOn[] = $queue;
            };
        };

        $this->assertTrue($inbox->process('notifications', $message, $handler('notifications')));
        $this->assertFalse($inbox->process('notifications', $message, $handler('notifications')));
        $this->assertTrue($inbox->process('audit', $message, $handler('audit')));

        $this->assertSame(['notifications', 'audit'], $handledOn);
        $this->assertSame(2, ProcessedMessage::query()->where('message_id', $message->messageId)->count());
    }

    public function test_a_failed_handler_rolls_back_its_writes_and_the_message_can_be_retried(): void
    {
        $inbox = app(InboxProcessor::class);
        $message = new Message(name: 'user.registered');

        try {
            $inbox->process('notifications', $message, function (): void {
                OutboxMessage::fromMessage(new Message(name: 'welcome.sent'))->save();

                throw new RuntimeException('SMTP is down');
            });

            $this->fail('The handler exception should propagate to the consumer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP is down', $exception->getMessage());
        }

        $this->assertDatabaseCount('processed_messages', 0);
        $this->assertDatabaseCount('outbox_messages', 0);

        $handled = false;

        $this->assertTrue($inbox->process('notifications', $message, function () use (&$handled): void {
            $handled = true;
        }));
        $this->assertTrue($handled);
    }

    public function test_processed_messages_older_than_the_retention_window_are_pruned(): void
    {
        $inbox = app(InboxProcessor::class);

        $this->travel(-31)->days();
        $inbox->process('notifications', $expired = new Message(name: 'user.registered'), fn () => null);
        $this->travelBack();

        $inbox->process('notifications', $recent = new Message(name: 'user.registered'), fn () => null);

        $this->artisan('model:prune', ['--model' => [ProcessedMessage::class]])->assertSuccessful();

        $this->assertDatabaseMissing('processed_messages', ['message_id' => $expired->messageId]);
        $this->assertDatabaseHas('processed_messages', ['message_id' => $recent->messageId]);
    }
}
