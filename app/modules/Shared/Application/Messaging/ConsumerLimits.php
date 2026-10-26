<?php

namespace App\Modules\Shared\Application\Messaging;

/**
 * When a long-running consumer should exit so its supervisor can start a
 * fresh process. Null means no limit.
 */
final readonly class ConsumerLimits
{
    public function __construct(
        public ?int $maxMessages = null,
        public ?int $maxSeconds = null,
        public ?int $maxMemoryMb = null,
    ) {
    }

    public function reached(int $handled, float $startedAt): bool
    {
        return ($this->maxMessages !== null && $handled >= $this->maxMessages)
            || ($this->maxSeconds !== null && microtime(true) - $startedAt >= $this->maxSeconds)
            || ($this->maxMemoryMb !== null && memory_get_usage(true) / 1024 / 1024 >= $this->maxMemoryMb);
    }
}
