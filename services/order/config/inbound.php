<?php

return [
    'exchange' => env('INBOUND_CATALOG_EXCHANGE', 'catalog.events'),
    'queue' => env('INBOUND_CATALOG_QUEUE', 'order.catalog-events'),
    'dlx_exchange' => env('INBOUND_CATALOG_DLX_EXCHANGE', 'order.catalog-events.dlx'),
    'dead_queue' => env('INBOUND_CATALOG_DEAD_QUEUE', 'order.catalog-events.dead'),
    'prefetch' => (int) env('INBOUND_CATALOG_PREFETCH', 1),
    'retry_delay_ms' => (int) env('INBOUND_CATALOG_RETRY_DELAY_MS', 500),
    'reconnect_delay_ms' => (int) env('INBOUND_CATALOG_RECONNECT_DELAY_MS', 1000),
    'wait_timeout' => (float) env('INBOUND_CATALOG_WAIT_TIMEOUT', 1.0),
    'delivery_limit' => (int) env('INBOUND_CATALOG_DELIVERY_LIMIT', 5),
];
