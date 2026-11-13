<?php

namespace App\Modules\Channel\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Database\Factories\ChannelFirmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An RNC firm on Franco's channel (rnc-3 R6, D5): a record of Franco's pipeline, not a tenant — no
 * login, no people's data. Its stage moves only through `ChannelService::advance`, which writes a
 * `ChannelFirmEvent` for each move.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $name
 * @property string $city
 * @property string $stage `ChannelStage`
 * @property CarbonImmutable $stage_at UTC
 * @property int $monthly_fee_cents
 * @property int $share_bp
 * @property string|null $notes
 */
#[Fillable(['name', 'city', 'stage', 'stage_at', 'monthly_fee_cents', 'share_bp', 'notes'])]
class ChannelFirm extends Model
{
    use BelongsToFirm;

    /** @use HasFactory<ChannelFirmFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage_at' => UtcDateTime::class,
            'monthly_fee_cents' => 'integer',
            'share_bp' => 'integer',
        ];
    }

    protected static function newFactory(): ChannelFirmFactory
    {
        return ChannelFirmFactory::new();
    }
}
