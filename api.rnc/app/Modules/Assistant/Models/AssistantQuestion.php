<?php

namespace App\Modules\Assistant\Models;

use App\Models\User;
use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question the assistant handed to a specialist (rnc-3 R13, R14): aberta until the specialist
 * answers it (F5).
 *
 * @property int $id
 * @property int $firm_id
 * @property int $asked_by
 * @property int $specialist_id
 * @property string $question
 * @property string $status `QuestionStatus`
 * @property string|null $answer
 * @property CarbonImmutable|null $answered_at UTC
 * @property-read User|null $specialist
 */
#[Fillable(['asked_by', 'specialist_id', 'question', 'status', 'answer', 'answered_at'])]
class AssistantQuestion extends Model
{
    use BelongsToFirm;

    /** @return BelongsTo<User, $this> */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    protected function casts(): array
    {
        return [
            'asked_by' => 'integer',
            'specialist_id' => 'integer',
            'answered_at' => UtcDateTime::class,
        ];
    }
}
