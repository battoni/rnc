<?php

namespace App\Modules\Firm\Models;

use App\Modules\Firm\Enums\PartnerMoneyModel;
use Database\Factories\FirmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A tenant: the accounting firm whose hub this is. Franco Contabilidade is firm 1; another firm is
 * another row, not another deploy. Its own look is the main brand; Express and Prime are its
 * sub-brands (`FirmBrand`, rnc-3 D1), not firms.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $logo_text
 * @property string $short_name
 * @property string|null $tagline
 * @property list<array{value: string, label: string}>|null $proof four points; null keeps the site's own copy
 * @property string|null $credentials the public footer's credentials line (rnc-4 F5a, W31); null shows none
 * @property string|null $logo_path a file on the `public` disk (rnc-4 F4c); null shows `logo_text`
 * @property list<string> $modules the opt-in module keys (rnc-4 D15); core is code, never stored
 * @property bool $is_platform
 * @property string $brand_primary
 * @property string $brand_accent
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $mailbox
 * @property list<array{weekdays: list<int>, from: string, to: string}> $business_hours
 * @property string $partner_money_model `PartnerMoneyModel` (rnc-2 R11)
 * @property int $partner_commission_rate_bp basis points: 1000 = 10 %
 * @property int $partner_monthly_fee_cents
 * @property Carbon|null $offboarded_at when `firm:offboard` closed it (rnc-4 F9a); null while on the platform
 */
#[Fillable(['name', 'slug', 'logo_text', 'short_name', 'tagline', 'proof', 'credentials', 'logo_path', 'brand_primary', 'brand_accent', 'address', 'phone', 'mailbox', 'business_hours', 'partner_money_model', 'partner_commission_rate_bp', 'partner_monthly_fee_cents', 'is_platform', 'modules', 'offboarded_at'])]
class Firm extends Model
{
    /** @use HasFactory<FirmFactory> */
    use HasFactory;

    /** The migration's defaults, so a firm just created reads them before any refresh (rnc-2 F4). */
    protected $attributes = [
        'partner_money_model' => 'comissao',
        'partner_commission_rate_bp' => 1000,
        'partner_monthly_fee_cents' => 49000,
        'is_platform' => false,
        // No database default on a json column (MySQL refuses one): a new firm opts into nothing.
        'modules' => '[]',
    ];

    /** The network's money model as its enum; an unknown stored value reads as the default. */
    public function partnerMoneyModel(): PartnerMoneyModel
    {
        return PartnerMoneyModel::tryFrom($this->partner_money_model) ?? PartnerMoneyModel::Comissao;
    }

    /** Whether `firm:offboard` closed the firm (rnc-4 R40): its host 404, its people refused. */
    public function isOffboarded(): bool
    {
        return $this->offboarded_at !== null;
    }

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'is_platform' => 'boolean',
            'offboarded_at' => 'datetime',
            'modules' => 'array',
            'proof' => 'array',
            'partner_commission_rate_bp' => 'integer',
            'partner_monthly_fee_cents' => 'integer',
        ];
    }

    protected static function newFactory(): FirmFactory
    {
        return FirmFactory::new();
    }
}
