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

    'cache' => [

        'version_ttl' => (int) env('CATALOG_CACHE_VERSION_TTL', 86400),

        'products' => [

            'list_ttl' => (int) env('CATALOG_CACHE_PRODUCTS_LIST_TTL', 300),

            'card_ttl' => (int) env('CATALOG_CACHE_PRODUCTS_CARD_TTL', 600),

        ],

        'categories' => [

            'tree_ttl' => (int) env('CATALOG_CACHE_CATEGORIES_TREE_TTL', 1800),

        ],

    ],

];
