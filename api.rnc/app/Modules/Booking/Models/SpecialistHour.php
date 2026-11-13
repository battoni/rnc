<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One weekday of a specialist's hours: ISO weekday (1 = segunda) and `HH:MM` local times (AM9).
 *
 * @property int $id
 * @property int $specialist_id
 * @property int $weekday
 * @property string $opens_at
 * @property string $closes_at
 */
#[Fillable(['specialist_id', 'weekday', 'opens_at', 'closes_at'])]
class SpecialistHour extends Model
{
    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
