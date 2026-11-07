<?php

namespace App\Modules\Partner\Models;

use App\Models\User;
use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Franco's recommendation of one lead to one partner (rnc-1 R22): the consent the lead gave, copied
 * at that moment, the score and the items it covered, the message and who sent it — then where the
 * partner took it (rnc-2 R7), the moments in UTC.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $lead_id
 * @property int $partner_id
 * @property int $referred_by
 * @property string $message
 * @property int $score
 * @property list<array{id: string, title: string}> $covers
 * @property string $consent_text
 * @property int $consent_version
 * @property Carbon $consent_given_at
 * @property string $status
 * @property CarbonImmutable|null $status_at
 * @property int|null $status_by
 * @property CarbonImmutable|null $contacted_at
 * @property CarbonImmutable|null $meeting_at
 * @property int|null $deal_value_cents
 * @property CarbonImmutable|null $closed_at
 * @property-read Lead $lead
 * @property-read Partner $partner
 * @property-read User $referrer
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['firm_id', 'lead_id', 'partner_id', 'referred_by', 'message', 'score', 'covers', 'consent_text', 'consent_version', 'consent_given_at', 'status', 'status_at', 'status_by', 'contacted_at', 'meeting_at', 'deal_value_cents', 'closed_at'])]
class Referral extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'covers' => 'array',
            'consent_given_at' => 'datetime',
            'score' => 'integer',
            'consent_version' => 'integer',
            'status_at' => UtcDateTime::class,
            'status_by' => 'integer',
            'contacted_at' => UtcDateTime::class,
            'meeting_at' => UtcDateTime::class,
            'deal_value_cents' => 'integer',
            'closed_at' => UtcDateTime::class,
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }
}
