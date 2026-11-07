<?php

namespace App\Modules\Partner\DTOs;

use App\Modules\Partner\Enums\ReferralStatus;
use Spatie\LaravelData\Data;

/**
 * A partner's move (`POST /api/portal/referrals/{id}/transition`, rnc-2 R7): where to, the meeting
 * for `reuniao` and the first-year contract value for `fechado` — null for every other move
 * (`TransitionReferralRequest` drops them).
 */
class TransitionReferralDTO extends Data
{
    public function __construct(
        public ReferralStatus $to,
        public ?string $meeting_at = null,
        public ?int $deal_value_cents = null,
    ) {}
}
