<?php

namespace App\Modules\Shared\Application\Messaging;
use Illuminate\Support\Str;
/**
 * Transport-agnostic message envelope. Modules build these; the messaging
 * infrastructure (RabbitMQ, or a fake in tests) decides how to ship them.
 */
final readonly class Message
{
    // Not promoted: a promoted readonly property is already initialised
    // when the body runs, so defaulting it there would throw.
    public string $messageId;

    /**
     * @param  non-empty-string  $name     Logical message/event name, also the AMQP routing key (e.g. "user.registered").
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public string $name,
        public array $payload = [],
        public array $headers = [],
        ?string $messageId = null,
        public ?string $correlationId = null,
    ) {
        $this->messageId = $messageId ?? (string) Str::uuid();
    }
}
