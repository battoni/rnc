<?php

namespace App\Modules\Firm\DTOs;

use App\Modules\Firm\Models\Firm;

/** What `FirmOffboarder::offboard` did (rnc-4 R40): the closed firm, its export file, how many leads it anonymised. */
final readonly class OffboardedFirm
{
    public function __construct(
        public Firm $firm,
        public string $path,
        public int $leads,
    ) {}
}
