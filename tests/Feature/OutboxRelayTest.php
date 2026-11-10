<?php

namespace Tests\Feature;

use App\Modules\Shared\Application\Messaging\Contracts\MessagePublisherInterface;
use App\Modules\Shared\Application\Messaging\Message;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxMessage;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $publishedNames = [];

    public function test_pending_messages_are_published_in_order_and_marked_published(): void
    {
        OutboxMessage::fromMessage(new Message(name: 'first'))->save();
        OutboxMessage::fromMessage(new Message(name: 'second'))->save();

        $this->assertSame(2, $this->relay()->relay(100));

        $this->assertSame(['first', 'second'], $this->publishedNames);
        $this->assertSame(0, OutboxMessage::query()->pending()->count());
    }

    public function test_nothing_is_published_while_another_relay_holds_the_lock(): void
    {
        OutboxMessage::fromMessage(new Message(name: 'first'))->save();

        $otherRelaysLock = Cache::lock('messaging:outbox-relay', 60);
        $this->assertTrue($otherRelaysLock->get());

        $this->assertSame(0, $this->relay()->relay(100));
        $this->assertSame([], $this->publishedNames);
        $this->assertSame(1, OutboxMessage::query()->pending()->count());

        $otherRelaysLock->release();

        $this->assertSame(1, $this->relay()->relay(100));
        $this->assertSame(['first'], $this->publishedNames);
    }

    private function relay(): OutboxRelay
    {
        $publishedNames = &$this->publishedNames;

        return new OutboxRelay(new class($publishedNames) implements MessagePublisherInterface
        {
            /**
             * @param  list<string>  $publishedNames
             */
            public function __construct(private array &$publishedNames) {}

            public function publish(Message $message): void
            {
                $this->publishedNames[] = $message->name;
            }
        });
    }
}
