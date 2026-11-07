<?php

namespace App\Modules\Idea\Models;

use App\Models\User;
use App\Modules\Idea\Enums\IdeaArea;
use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Enums\IdeaType;
use Carbon\CarbonImmutable;
use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Casts and computed columns, stated because static analysis reads the columns as strings.
 *
 * @property IdeaType $type
 * @property IdeaArea|null $area
 * @property IdeaStatus $status
 * @property CarbonImmutable|null $hidden_at
 * @property CarbonImmutable|null $delivered_at
 * @property bool|null $voted Set by the board query (`withExists`) or by `IdeaService`; absent on a bare row.
 */
#[Fillable([
    'user_id',
    'title',
    'description',
    'type',
    'area',
    'status',
    'admin_note',
    'votes_count',
    'comments_count',
    'merged_into_id',
    'hidden_at',
    'delivered_at',
])]
class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use HasFactory;

    /**
     * Laravel guesses factory names from the App\Models namespace; these models live in a
     * module, so the mapping has to be stated.
     */
    protected static function newFactory(): IdeaFactory
    {
        return IdeaFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => IdeaType::class,
            'area' => IdeaArea::class,
            'status' => IdeaStatus::class,
            'votes_count' => 'integer',
            'comments_count' => 'integer',
            'hidden_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /** @return HasOne<IdeaAttachment, $this> */
    public function attachment(): HasOne
    {
        return $this->hasOne(IdeaAttachment::class);
    }

    /** @return HasMany<IdeaComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(IdeaComment::class);
    }

    /**
     * The canonical idea this one was merged into, when it was a duplicate.
     *
     * @return BelongsTo<Idea, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<IdeaVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(IdeaVote::class);
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    public function isAuthoredBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * The one definition of "may rewrite this idea", used by the service that enforces it and by
     * the resource that tells the client whether to offer the button. Two copies of this rule is
     * how the button and the endpoint start disagreeing.
     *
     * The author owns the text only while nobody has acted on it: the maintainer's public note
     * answers these words, and every supporter supported these words. A moderator is exempt —
     * fixing a title is part of running a board.
     */
    public function isEditableBy(int $userId, bool $canModerate): bool
    {
        if ($canModerate) {
            return true;
        }

        $isUntouchedDraft = $this->status->isAuthorEditable() && $this->votes_count === 0;

        return $this->isAuthoredBy($userId) && $isUntouchedDraft;
    }
}
