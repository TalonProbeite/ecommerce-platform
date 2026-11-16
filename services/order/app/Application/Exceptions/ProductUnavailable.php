<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class ProductUnavailable extends RuntimeException
{
    public function __construct(public readonly string $productId)
    {
        parent::__construct(sprintf(
            'Product "%s" is missing, deleted, or inactive.',
            $productId,
        ));
    }
}
