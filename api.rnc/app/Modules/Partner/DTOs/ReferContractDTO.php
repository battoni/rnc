<?php

namespace App\Modules\Partner\DTOs;

use Spatie\LaravelData\Data;

/** A legal referral (`POST /api/contracts/{id}/referrals`, rnc-3 R20): to whom. The message is the demo's. */
class ReferContractDTO extends Data
{
    public function __construct(
        public int $partner_id,
    ) {}
}
