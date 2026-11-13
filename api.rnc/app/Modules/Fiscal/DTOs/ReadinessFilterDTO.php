<?php

namespace App\Modules\Fiscal\DTOs;

use Spatie\LaravelData\Data;

/** The readiness list's filters (`GET /api/fiscal/readiness?status=&system=`, R19); none is every client. */
class ReadinessFilterDTO extends Data
{
    public function __construct(
        public ?string $status = null,
        public ?string $system = null,
    ) {}
}
