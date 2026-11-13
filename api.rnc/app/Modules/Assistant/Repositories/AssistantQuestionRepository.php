<?php

namespace App\Modules\Assistant\Repositories;

use App\Models\User;
use App\Modules\Assistant\Enums\QuestionStatus;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Auth\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** The current firm's handed-off questions, and the specialists they can go to. */
class AssistantQuestionRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function createQuestion(array $attributes): AssistantQuestion
    {
        return AssistantQuestion::query()->create($attributes);
    }

    /**
     * The inbox (R14): the questions handed to `$specialistId`, or every one of the firm when null —
     * open ones first, then the newest; with the asker and the specialist (three queries).
     *
     * @return Collection<int, AssistantQuestion>
     */
    public function findInboxQuestions(?int $specialistId): Collection
    {
        return AssistantQuestion::query()
            ->when($specialistId !== null, fn ($query) => $query->where('specialist_id', $specialistId))
            ->with(['asker', 'specialist'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [QuestionStatus::Aberta->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findQuestionById(int $id): ?AssistantQuestion
    {
        return AssistantQuestion::query()->find($id);
    }

    /**
     * Answer the question unless somebody already did: true when this call did (one conditional
     * update, so two answers sent at once never both land nor ring the asker twice).
     */
    public function claimAnswer(AssistantQuestion $question, string $answer, CarbonImmutable $now): bool
    {
        return AssistantQuestion::query()
            ->whereKey($question->id)
            ->where('status', QuestionStatus::Aberta->value)
            ->update([
                'status' => QuestionStatus::Respondida->value,
                'answer' => $answer,
                'answered_at' => $now->utc()->format('Y-m-d H:i:s'),
                'updated_at' => $now->utc()->format('Y-m-d H:i:s'),
            ]) === 1;
    }

    /** Tie the question to the note "Virar nota" made, unless it already has one: true when this call did. */
    public function claimQuestionNote(AssistantQuestion $question, int $noteId): bool
    {
        return AssistantQuestion::query()->whereKey($question->id)->whereNull('note_id')->update(['note_id' => $noteId]) === 1;
    }

    /**
     * The firm's active specialists, by name — the hand-off's picker, its first entry the default
     * (R13). `users` is not firm-scoped, so the firm is asked here.
     *
     * @return Collection<int, User>
     */
    public function findActiveSpecialistsOfFirm(int $firmId, ?int $exceptUserId = null): Collection
    {
        return User::query()
            ->where('firm_id', $firmId)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->where('is_specialist', true)
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();
    }
}
