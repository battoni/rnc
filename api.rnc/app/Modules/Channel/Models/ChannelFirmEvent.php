<?php

namespace App\Modules\Channel\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One stage move of a channel firm (rnc-3 R7): who, from, to, at. Written only by
 * `ChannelService::advance`; never edited.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $channel_firm_id
 * @property int $user_id
 * @property string $from `ChannelStage`
 * @property string $to `ChannelStage`
 * @property CarbonImmutable $at UTC
 */
#[Fillable(['channel_firm_id', 'user_id', 'from', 'to', 'at'])]
class ChannelFirmEvent extends Model
{
    use BelongsToFirm;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'channel_firm_id' => 'integer',
            'user_id' => 'integer',
            'at' => UtcDateTime::class,
        ];
    }
}
