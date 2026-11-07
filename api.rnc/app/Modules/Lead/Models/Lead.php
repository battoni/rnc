<?php

namespace App\Modules\Lead\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property-read LeadConsent|null $latestConsent
 */
#[Fillable(['token', 'token_expires_at', 'report_token', 'report_expires_at', 'visitor_uuid', 'stage', 'last_step', 'path', 'company', 'cnpj', 'contact_name', 'phone', 'email', 'profile', 'channel', 'source', 'medium'])]
class Lead extends Model
{
    use BelongsToFirm;

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'report_expires_at' => 'datetime',
            'profile' => 'array',
        ];
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
