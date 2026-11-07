<?php

namespace App\Modules\Partner\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use App\Modules\Partner\Enums\PartnerSpecialty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['firm_id', 'name', 'specialty', 'email', 'phone', 'city', 'active'])]
class Partner extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function specialtyCase(): PartnerSpecialty
    {
        return PartnerSpecialty::from($this->specialty);
    }
}
