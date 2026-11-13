<?php

return [
    'exchange' => env('INBOUND_ORDER_EXCHANGE', 'order.events'),
    'queue' => env('INBOUND_ORDER_QUEUE', 'catalog.order-events'),
    'dlx_exchange' => env('INBOUND_ORDER_DLX_EXCHANGE', 'catalog.order-events.dlx'),
    'dead_queue' => env('INBOUND_ORDER_DEAD_QUEUE', 'catalog.order-events.dead'),
    'prefetch' => (int) env('INBOUND_ORDER_PREFETCH', 1),
    'retry_delay_ms' => (int) env('INBOUND_ORDER_RETRY_DELAY_MS', 500),
    'reconnect_delay_ms' => (int) env('INBOUND_ORDER_RECONNECT_DELAY_MS', 1000),
    'wait_timeout' => (float) env('INBOUND_ORDER_WAIT_TIMEOUT', 1.0),
    'delivery_limit' => (int) env('INBOUND_ORDER_DELIVERY_LIMIT', 5),
];
