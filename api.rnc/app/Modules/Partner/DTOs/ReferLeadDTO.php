<?php

namespace App\Modules\Partner\DTOs;

use Spatie\LaravelData\Data;

/** A referral (`POST /api/leads/{id}/referrals`): to whom, and the message the partner reads. */
class ReferLeadDTO extends Data
{
    public function __construct(
        public int $partner_id,
        public string $message,
    ) {}
}
