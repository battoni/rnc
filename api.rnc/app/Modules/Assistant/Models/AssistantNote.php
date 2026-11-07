<?php

namespace App\Modules\Assistant\Models;

use App\Models\User;
use App\Modules\Assistant\Enums\NoteStatus;
use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Database\Factories\AssistantNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One note of Franco's Reforma knowledge base (rnc-3 R10, R11): a question, Franco's answer, the
 * legal rule, the source and the day it was read. It answers a question only while it is SERVED —
 * revisada and reviewed at most `VALID_DAYS` days ago, counted in São Paulo calendar days so day 90
 * is served to its last minute and day 91 is not (D8, AM7, W52).
 *
 * @property int $id
 * @property int $firm_id
 * @property string $slug
 * @property string $question
 * @property string $answer
 * @property string $rule
 * @property string $source_label
 * @property string $source_url
 * @property CarbonImmutable $as_of
 * @property list<string> $keywords_strong
 * @property list<string> $keywords_weak
 * @property string $status `NoteStatus`
 * @property int|null $reviewer_id
 * @property CarbonImmutable|null $reviewed_at UTC
 * @property int|null $last_edited_by
 * @property CarbonImmutable|null $expiry_notified_at UTC — the reviewer's 7-day bell (AM19)
 * @property-read User|null $reviewer
 * @property-read User|null $lastEditor
 */
#[Fillable(['slug', 'question', 'answer', 'rule', 'source_label', 'source_url', 'as_of', 'keywords_strong', 'keywords_weak', 'status', 'reviewer_id', 'reviewed_at', 'last_edited_by', 'expiry_notified_at'])]
class AssistantNote extends Model
{
    use BelongsToFirm;

    /** @use HasFactory<AssistantNoteFactory> */
    use HasFactory;

    /** How long a review is served (D8, W10). */
    public const VALID_DAYS = 90;

    /** Where the 90 days are counted (plan P4). */
    public const BUSINESS_TIMEZONE = 'America/Sao_Paulo';

    /** How long before the review runs out the base counts it and the reviewer's bell rings: day 83 (AM19). */
    public const WARNING_DAYS = 7;

    /** The earliest review still served at `$now`: the start of the São Paulo day `VALID_DAYS` back. */
    public static function servedSince(CarbonImmutable $now): CarbonImmutable
    {
        return $now->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay()->subDays(self::VALID_DAYS)->utc();
    }

    /**
     * The latest review that is already in its last `WARNING_DAYS` days at `$now`: a review before the
     * start of the São Paulo day `VALID_DAYS − WARNING_DAYS − 1` back is at least 83 days old.
     */
    public static function warnedBefore(CarbonImmutable $now): CarbonImmutable
    {
        return $now->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay()->subDays(self::VALID_DAYS - self::WARNING_DAYS - 1)->utc();
    }

    /**
     * The notes the assistant may answer with at `$now`.
     *
     * @param  Builder<AssistantNote>  $query
     */
    public function scopeServed(Builder $query, CarbonImmutable $now): void
    {
        $query->where('status', NoteStatus::Revisada->value)
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '>=', self::servedSince($now)->format('Y-m-d H:i:s'));
    }

    /**
     * Served notes in their last `WARNING_DAYS` days (83–90 days old): "N notas vencem em 7 dias".
     *
     * @param  Builder<AssistantNote>  $query
     */
    public function scopeExpiringSoon(Builder $query, CarbonImmutable $now): void
    {
        $query->served($now)->where('reviewed_at', '<', self::warnedBefore($now)->format('Y-m-d H:i:s'));
    }

    /** São Paulo days the review has left at `$now` — 0 on day 90; null when the note is not served. */
    public function expiresInDays(CarbonImmutable $now): ?int
    {
        if (! $this->isServedAt($now) || $this->reviewed_at === null) {
            return null;
        }

        $reviewDay = $this->reviewed_at->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay();
        $today = $now->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay();

        return self::VALID_DAYS - (int) $reviewDay->diffInDays($today);
    }

    /** The last São Paulo day the review is served: the review's day plus `VALID_DAYS`. */
    public function expiresOn(): ?CarbonImmutable
    {
        return $this->reviewed_at?->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay()->addDays(self::VALID_DAYS);
    }

    public function isServedAt(CarbonImmutable $now): bool
    {
        return $this->status === NoteStatus::Revisada->value
            && $this->reviewed_at !== null
            && $this->reviewed_at->greaterThanOrEqualTo(self::servedSince($now));
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    protected function casts(): array
    {
        return [
            'as_of' => 'immutable_date',
            'keywords_strong' => 'array',
            'keywords_weak' => 'array',
            'reviewer_id' => 'integer',
            'reviewed_at' => UtcDateTime::class,
            'last_edited_by' => 'integer',
            'expiry_notified_at' => UtcDateTime::class,
        ];
    }

    protected static function newFactory(): AssistantNoteFactory
    {
        return AssistantNoteFactory::new();
    }
}
