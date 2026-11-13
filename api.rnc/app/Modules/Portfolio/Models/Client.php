<?php

namespace App\Modules\Portfolio\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A company the firm already serves (rnc-1 R25). One per CNPJ per firm.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $name
 * @property string $cnpj
 * @property string $sector
 * @property string $regime
 * @property string $band
 * @property int $b2b_share
 * @property string $monthly_fee
 * @property array{at: string, by: int, pct: int, suggested: float}|null $repricing
 * @property bool|null $partner_consent may the firm introduce it to partners (rnc-2 R28): null = never answered
 * @property CarbonImmutable|null $partner_consent_at UTC
 * @property string|null $partner_consent_via `contrato`
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['firm_id', 'name', 'cnpj', 'sector', 'regime', 'band', 'b2b_share', 'monthly_fee', 'repricing', 'partner_consent', 'partner_consent_at', 'partner_consent_via'])]
class Client extends Model
{
    use BelongsToFirm;

    /** How a client's consent is given: the partner clause of the services contract (rnc-2 R28). */
    public const CONSENT_VIA_CONTRACT = 'contrato';

    protected function casts(): array
    {
        return [
            'b2b_share' => 'integer',
            'monthly_fee' => 'decimal:2',
            'repricing' => 'array',
            'partner_consent' => 'boolean',
            'partner_consent_at' => UtcDateTime::class,
        ];
    }

    /** What the rules engine reads. The CSV carries no ICMS incentive and no export flag: both false. */
    public function profile(): DiagnosticProfileDTO
    {
        return new DiagnosticProfileDTO(sector: $this->sector, regime: $this->regime, band: $this->band, b2b: $this->b2b_share);
    }

    public function fee(): float
    {
        return (float) $this->monthly_fee;
    }
}
