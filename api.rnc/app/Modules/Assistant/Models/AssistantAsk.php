<?php

namespace App\Modules\Assistant\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One question put to the assistant (rnc-3 R15): who, the words, the words as the matcher read them,
 * the note that answered or null, and when. Written once by `AskService`, never edited.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $user_id
 * @property string $question
 * @property string $normalised
 * @property int|null $note_id
 * @property CarbonImmutable $at UTC
 */
#[Fillable(['user_id', 'question', 'normalised', 'note_id', 'at'])]
class AssistantAsk extends Model
{
    use BelongsToFirm;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'note_id' => 'integer',
            'at' => UtcDateTime::class,
        ];
    }
}
