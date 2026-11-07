<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of a visitor on the public site — a page view, a CTA click, a diagnostic step (rnc-1 R7).
 * Named `TrackingEvent` so it never reads as Laravel's `Event` facade; the table is `events`.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $visitor_id
 * @property string $type
 * @property string $path
 * @property string|null $sector
 * @property string $source
 * @property string|null $medium
 * @property int|null $lead_id
 * @property Carbon $at
 */
#[Fillable(['firm_id', 'visitor_id', 'type', 'path', 'sector', 'source', 'medium', 'lead_id', 'at'])]
class TrackingEvent extends Model
{
    use BelongsToFirm;

    protected $table = 'events';

    protected function casts(): array
    {
        return [
            'at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
