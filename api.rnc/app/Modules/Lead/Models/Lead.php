<?php

namespace App\Modules\Lead\Models;

use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Traits\BelongsToFirm;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A prospect from the public diagnostic (rnc-1 R11). The public routes know it only by `token`.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $token
 * @property Carbon $token_expires_at
 * @property string|null $report_token
 * @property Carbon|null $report_expires_at
 * @property string|null $visitor_uuid
 * @property string $stage
 * @property string|null $last_step
 * @property string|null $lost_reason
 * @property string $path
 * @property string|null $company
 * @property string|null $cnpj
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property array<string, mixed> $profile
 * @property string $channel
 * @property string $source
 * @property string|null $medium
 * @property int|null $brand_id
 * @property-read FirmBrand|null $brand
 * @property-read LeadConsent|null $latestConsent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['token', 'token_expires_at', 'report_token', 'report_expires_at', 'visitor_uuid', 'stage', 'last_step', 'lost_reason', 'path', 'company', 'cnpj', 'contact_name', 'phone', 'email', 'profile', 'channel', 'source', 'medium', 'brand_id'])]
class Lead extends Model
{
    use BelongsToFirm;

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * The main brand in a brand filter or count (rnc-3 R4): `brand_id` null. Ids start at 1, so 0
     * names no sub-brand.
     */
    public const MAIN_BRAND = 0;

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'report_expires_at' => 'datetime',
            'profile' => 'array',
        ];
    }

    /** @return BelongsTo<FirmBrand, $this> the sub-brand it was started under; null is the main brand */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(FirmBrand::class, 'brand_id');
    }

    /** @return HasMany<LeadConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(LeadConsent::class);
    }

    /** @return HasOne<LeadConsent, $this> */
    public function latestConsent(): HasOne
    {
        return $this->hasOne(LeadConsent::class)->latestOfMany();
    }

    /** @return HasMany<LeadEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }

    public function hasEmail(): bool
    {
        return $this->email !== null;
    }

    public function hasContact(): bool
    {
        return $this->phone !== null || $this->email !== null;
    }

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }
}
