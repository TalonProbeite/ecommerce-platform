<?php

return [

    'product_images' => [

        'disk' => env('CATALOG_IMAGES_DISK', 'seaweedfs'),

        'max_per_product' => (int) env('CATALOG_IMAGES_MAX_PER_PRODUCT', 10),

        'allowed_mime_types' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],

    ],

];
