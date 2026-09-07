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
];