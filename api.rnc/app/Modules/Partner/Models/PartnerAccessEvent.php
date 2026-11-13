<?php

namespace App\Modules\Partner\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One grant or revoke of a partner's portal login (rnc-2 R2, plan AM9). Written only by
 * `PartnerAccessService`; never edited. The ledger's subscription months (F4) are read from these.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $partner_id
 * @property int $user_id
 * @property string $type `PartnerAccessEventType`
 * @property CarbonImmutable $at UTC
 */
#[Fillable(['firm_id', 'partner_id', 'user_id', 'type', 'at'])]
class PartnerAccessEvent extends Model
{
    use BelongsToFirm;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'partner_id' => 'integer',
            'user_id' => 'integer',
            'at' => UtcDateTime::class,
        ];
    }
}
