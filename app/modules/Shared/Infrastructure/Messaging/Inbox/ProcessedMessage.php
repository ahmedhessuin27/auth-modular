<?php

namespace App\Modules\Shared\Infrastructure\Messaging\Inbox;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (queue, message_id) a consumer has handled; InboxProcessor
 * uses it to skip redelivered and duplicated messages.
 */
class ProcessedMessage extends Model
{
    use MassPrunable;

    /**
     * Must outlast any realistic redelivery, including dead-lettered
     * messages being replayed by hand.
     */
    private const KEEP_PROCESSED_DAYS = 30;

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where(
            'processed_at',
            '<=',
            now()->subDays(self::KEEP_PROCESSED_DAYS)
        );
    }
}
