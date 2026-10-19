<?php

declare(strict_types=1);

namespace App\DTO;

if (! class_exists(ProductFilters::class, false)) {
    require_once __DIR__ . '/ProductFilters.php';
}

class_alias(ProductFilters::class, ProductFilter::class);
