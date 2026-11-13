<?php

namespace App\Modules\Fiscal\DTOs;

use Spatie\LaravelData\Data;

/** The supplier-credit list's filter (`GET /api/fiscal/supplier-credit?sector=`, R22); none is every sector. */
class SupplierCreditFilterDTO extends Data
{
    public function __construct(
        public ?string $sector = null,
    ) {}
}
