<?php

return [
    'host' => env('RABBITMQ_HOST'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER'),
    'password' => env('RABBITMQ_PASSWORD'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'ssl' => filter_var(
        env('RABBITMQ_SSL', false),
        FILTER_VALIDATE_BOOLEAN
    ),
    // Seconds; 0 disables. Socket read/write timeouts are derived from it.
    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 60),
    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 10.0),
    // Topic exchange every integration event is published to.
    'exchange' => env('RABBITMQ_EXCHANGE', 'modular.events'),
    // Max unacknowledged deliveries a consumer holds at once.
    'prefetch_count' => (int) env('RABBITMQ_PREFETCH_COUNT', 10),
    // Seconds to wait for the broker to confirm a publish.
    'confirm_timeout' => (float) env('RABBITMQ_CONFIRM_TIMEOUT', 5.0),
    'retry' => [
        // Retries after the first failed attempt, before dead-lettering.
        'max_retries' => (int) env('RABBITMQ_RETRY_MAX_RETRIES', 3),
        // Baked into each retry queue's x-message-ttl: after changing it,
        // delete the *.retry.queue queues and re-run rabbitmq:setup.
        'delay_ms' => (int) env('RABBITMQ_RETRY_DELAY_MS', 5000),
    ],
];
