<?php

namespace App\Modules\Partner\Models;

use App\Models\User;
use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use App\Modules\Partner\Enums\PartnerSpecialty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A specialist of the firm's network (rnc-1 R21). Deactivated, never deleted.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $name
 * @property string $specialty
 * @property string $email
 * @property string|null $phone
 * @property string|null $city
 * @property bool $active
 * @property CarbonImmutable|null $access_granted_at UTC — the current portal login's grant (rnc-2 R2)
 * @property CarbonImmutable|null $access_revoked_at UTC — its revoke; the history is `partner_access_events`
 * @property-read User|null $account the portal login, active or closed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['firm_id', 'name', 'specialty', 'email', 'phone', 'city', 'active', 'access_granted_at', 'access_revoked_at'])]
class Partner extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'access_granted_at' => UtcDateTime::class,
            'access_revoked_at' => UtcDateTime::class,
        ];
    }

    public function specialtyCase(): PartnerSpecialty
    {
        return PartnerSpecialty::from($this->specialty);
    }

    /**
     * The partner's portal login (rnc-2 R1): a `users` row of Auth's, read here (declared in
     * RULES.md), written only through `LinkedAccountService`. At most one per partner.
     *
     * @return HasOne<User, $this>
     */
    public function account(): HasOne
    {
        return $this->hasOne(User::class, 'partner_id')->latestOfMany();
    }
}
