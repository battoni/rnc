<?php

namespace App\Modules\Radar\DTOs;

use App\Modules\Diagnostic\Enums\DiagnosticRule;
use Spatie\LaravelData\Data;

/** A Radar batch (`POST /api/radar/referrals`, rnc-2 R27): which row, to which partner. */
class ReferRadarBatchDTO extends Data
{
    public function __construct(
        public DiagnosticRule $rule,
        public int $partner_id,
    ) {}
}
