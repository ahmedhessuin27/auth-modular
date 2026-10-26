<?php

namespace App\Modules\Shared\Infrastructure\Messaging\Outbox;

use App\Modules\Shared\Application\Messaging\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    use MassPrunable;

    private const KEEP_PUBLISHED_DAYS = 7;

    protected $fillable = [
        'message_id',
        'name',
        'payload',
        'headers',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'attempts' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public static function fromMessage(Message $message): self
    {
        return new self([
            'message_id' => $message->messageId,
            'name' => $message->name,
            'payload' => $message->payload,
            'headers' => $message->headers,
            'correlation_id' => $message->correlationId,
        ]);
    }

    public function toMessage(): Message
    {
        return new Message(
            name: $this->name,
            payload: $this->payload,
            headers: $this->headers,
            messageId: $this->message_id,
            correlationId: $this->correlation_id,
        );
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('published_at');
    }

    /**
     * Published rows are only kept for troubleshooting.
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'published_at',
            '<=',
            now()->subDays(self::KEEP_PUBLISHED_DAYS)
        );
    }
}
