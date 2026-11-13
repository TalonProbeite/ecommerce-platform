<?php

return [
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),
    'idle_sleep_ms' => (int) env('OUTBOX_IDLE_SLEEP_MS', 100),
    'max_batches' => env('OUTBOX_MAX_BATCHES') !== null ? (int) env('OUTBOX_MAX_BATCHES') : null,
    'observer_enabled' => (bool) env('OUTBOX_OBSERVER_ENABLED', false),
];
