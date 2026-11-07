<?php

namespace App\Modules\Assistant\Services;

use App\Models\User;
use App\Modules\Assistant\DTOs\QuestionDTO;
use App\Modules\Assistant\Enums\QuestionStatus;
use App\Modules\Assistant\Exceptions\AssistantException;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Assistant\Repositories\AssistantQuestionRepository;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Notification\Notifications\AssistantQuestionNotification;
use App\Modules\Notification\Services\TeamNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The hand-off (rnc-3 R13, W11, W56): a question no served note answers goes to an active
 * specialist of the firm — the one picked, or the first by name — as an open question and a bell
 * on that specialist alone. Anyone else is 422 `assistant.notSpecialist`. Answering is F5's.
 */
class QuestionService
{
    public function __construct(
        private AssistantQuestionRepository $questionRepository,
        private CurrentFirm $currentFirm,
        private TeamNotifier $teamNotifier,
    ) {}

    /** @return Collection<int, User> the firm's active specialists, by name — the picker, default first */
    public function specialists(): Collection
    {
        $firmId = $this->currentFirm->id();

        return $firmId === null ? new Collection : $this->questionRepository->findActiveSpecialistsOfFirm($firmId);
    }

    public function handOff(User $asker, QuestionDTO $question): AssistantQuestion
    {
        $specialists = $this->specialists();
        $specialist = $question->specialist_id === null ? $specialists->first() : $specialists->firstWhere('id', $question->specialist_id);

        if (! $specialist) {
            throw AssistantException::notSpecialist();
        }

        return DB::transaction(function () use ($asker, $question, $specialist) {
            $created = $this->questionRepository->createQuestion([
                'asked_by' => $asker->id,
                'specialist_id' => $specialist->id,
                'question' => $question->question,
                'status' => QuestionStatus::Aberta->value,
            ]);

            $this->teamNotifier->notifyUser($specialist, new AssistantQuestionNotification(
                questionId: $created->id,
                question: $created->question,
                askedBy: trim($asker->name.' '.$asker->last_name),
            ));

            return $created->setRelation('specialist', $specialist);
        });
    }
}
