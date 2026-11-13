<?php

namespace App\Modules\Booking\Models;

use App\Models\User;
use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team member a visitor can book (rnc-1 R15). The person — name, e-mail — is the `users` row;
 * this row holds what the agenda needs: the area shown, the focus and the weekly hours.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $user_id
 * @property string|null $area
 * @property string|null $focus
 * @property-read User $user
 * @property-read Collection<int, SpecialistHour> $hours
 */
#[Fillable(['firm_id', 'user_id', 'area', 'focus'])]
class Specialist extends Model
{
    use BelongsToFirm;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SpecialistHour, $this> */
    public function hours(): HasMany
    {
        return $this->hasMany(SpecialistHour::class);
    }

    public function displayName(): string
    {
        return trim($this->user->name.' '.$this->user->last_name);
    }
}
