<?php

namespace App\Modules\Lead\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A line of a lead's timeline (rnc-1 R19). `user_id` null = the visitor did it.
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property string $type
 * @property array<string, mixed>|null $data
 * @property Carbon $at
 * @property-read User|null $user
 */
#[Fillable(['lead_id', 'user_id', 'type', 'data', 'at'])]
class LeadEvent extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> who did it; null for the visitor */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
