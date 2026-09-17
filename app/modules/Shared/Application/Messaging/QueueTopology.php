<?php

namespace App\Modules\Shared\Application\Messaging;

final readonly class QueueTopology
{
    /**
     * @param array<int, string> $bindings
     */
    public function __construct(
        public string $queue,
        public array $bindings = [],
    ) {
    }
}