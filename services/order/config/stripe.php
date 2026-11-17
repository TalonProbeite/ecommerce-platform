<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe API
    |--------------------------------------------------------------------------
    |
    | зафиксировать перед P8
    |
    */

    'secret' => env('STRIPE_SECRET'),

    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    'api_version' => env('STRIPE_API_VERSION') ?: null,

    'version' => env('STRIPE_API_VERSION', ''),

    'currency' => 'eur',

    'timeout' => 10,

];
