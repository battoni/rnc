<?php

namespace App\Modules\Portfolio\Models;

use App\Models\User;
use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a client's timeline (rnc-2 R20, R22, D12). `user_id` null = no person (a seed).
 *
 * @property int $id
 * @property int $firm_id
 * @property int $client_id
 * @property int|null $user_id
 * @property string $type `ClientEventType`
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable $at UTC
 * @property-read User|null $user
 */
#[Fillable(['firm_id', 'client_id', 'user_id', 'type', 'data', 'at'])]
class ClientEvent extends Model
{
    use BelongsToFirm;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'user_id' => 'integer',
            'data' => 'array',
            'at' => UtcDateTime::class,
        ];
    }

    /** @return BelongsTo<User, $this> who did it */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
