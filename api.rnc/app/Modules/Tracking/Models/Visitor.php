<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An anonymous visitor of the public site: the UUID its browser keeps, and the source of its first
 * visit (rnc-1 R7). No IP, no user agent, no raw referrer.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $uuid
 * @property string $source
 * @property string|null $medium
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
#[Fillable(['firm_id', 'uuid', 'source', 'medium', 'first_seen_at', 'last_seen_at'])]
class Visitor extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return HasMany<TrackingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
