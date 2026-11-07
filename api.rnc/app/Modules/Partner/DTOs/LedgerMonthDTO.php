<?php

namespace App\Modules\Partner\DTOs;

use Spatie\LaravelData\Data;

/** The month a ledger answer covers, `YYYY-MM`; `null` is this month in São Paulo. */
class LedgerMonthDTO extends Data
{
    public function __construct(
        public ?string $month = null,
    ) {}
}
