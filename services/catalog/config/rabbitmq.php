<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER'),
    'password' => env('RABBITMQ_PASSWORD'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    // Reconnect after half a heartbeat interval of inactivity in HTTP workers.
    'heartbeat' => 10,
    'connection_timeout' => 3.0,
    'read_write_timeout' => 25.0,
    'channel_rpc_timeout' => 5.0,
    'confirm_timeout' => 5.0,

    'exchange' => [
        'name' => env('RABBITMQ_EXCHANGE', 'catalog.events'),
        'type' => 'topic',
        'durable' => true,
    ],
];
