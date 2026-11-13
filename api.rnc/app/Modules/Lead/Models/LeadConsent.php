<?php

namespace App\Modules\Lead\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One answer to the partner-introduction checkbox (rnc-1 R11, AM12). Scoped through its lead.
 *
 * @property int $id
 * @property int $lead_id
 * @property string $text
 * @property int $version
 * @property bool $given
 * @property string $via
 * @property Carbon $given_at
 */
#[Fillable(['lead_id', 'text', 'version', 'given', 'via', 'given_at'])]
class LeadConsent extends Model
{
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'given' => 'boolean',
            'given_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
