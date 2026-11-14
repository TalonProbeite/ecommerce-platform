<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    // Reconnect after half a heartbeat interval of inactivity in HTTP workers.
    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 10),
    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 25.0),
    'channel_rpc_timeout' => (float) env('RABBITMQ_CHANNEL_RPC_TIMEOUT', 5.0),
    'confirm_timeout' => (float) env('RABBITMQ_CONFIRM_TIMEOUT', 5.0),

    'exchange' => [
        'name' => env('RABBITMQ_EXCHANGE', 'order.events'),
        'type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
        'durable' => (bool) env('RABBITMQ_EXCHANGE_DURABLE', true),
    ],
];
