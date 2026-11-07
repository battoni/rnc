<?php

namespace App\Modules\Booking\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booked meeting (rnc-1 R16): one lead, one specialist, one 30-minute slot, stored in UTC (AM9).
 *
 * @property int $id
 * @property int $firm_id
 * @property int $lead_id
 * @property int $specialist_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $reminded_at
 * @property-read Lead $lead
 * @property-read Specialist $specialist
 */
#[Fillable(['firm_id', 'lead_id', 'specialist_id', 'starts_at', 'ends_at', 'reminded_at'])]
class Appointment extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'reminded_at' => UtcDateTime::class,
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Specialist, $this> */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }
}
