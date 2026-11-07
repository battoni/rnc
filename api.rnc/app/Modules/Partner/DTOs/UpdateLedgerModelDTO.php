<?php

namespace App\Modules\Partner\DTOs;

use App\Modules\Firm\Enums\PartnerMoneyModel;
use Spatie\LaravelData\Data;

/** The money model the whole network moves to (rnc-2 R11, D10). */
class UpdateLedgerModelDTO extends Data
{
    public function __construct(
        public PartnerMoneyModel $model,
    ) {}
}
