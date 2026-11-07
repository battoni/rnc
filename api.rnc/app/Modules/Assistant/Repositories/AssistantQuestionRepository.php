<?php

namespace App\Modules\Assistant\Repositories;

use App\Models\User;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Auth\Enums\UserStatus;
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
     * The firm's active specialists, by name — the hand-off's picker, its first entry the default
     * (R13). `users` is not firm-scoped, so the firm is asked here.
     *
     * @return Collection<int, User>
     */
    public function findActiveSpecialistsOfFirm(int $firmId): Collection
    {
        return User::query()
            ->where('firm_id', $firmId)
            ->where('is_specialist', true)
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();
    }
}
