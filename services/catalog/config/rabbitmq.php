<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER'),
    'password' => env('RABBITMQ_PASSWORD'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    'exchange' => [
        'name' => env('RABBITMQ_EXCHANGE', 'catalog.events'),
        'type' => 'topic',
        'durable' => true,
    ],
];
