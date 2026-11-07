<?php

namespace App\Modules\Assistant\Services;

use App\Models\User;
use App\Modules\Assistant\DTOs\QuestionDTO;
use App\Modules\Assistant\Enums\QuestionStatus;
use App\Modules\Assistant\Exceptions\AssistantException;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Assistant\Policies\AssistantNotePolicy;
use App\Modules\Assistant\Repositories\AssistantQuestionRepository;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Notification\Notifications\AssistantAnswerNotification;
use App\Modules\Notification\Notifications\AssistantQuestionNotification;
use App\Modules\Notification\Services\TeamNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The hand-off (rnc-3 R13, W11, W56): a question no served note answers goes to an active
 * specialist of the firm — the one picked, or the first by name — as an open question and a bell
 * on that specialist alone. Anyone else is 422 `assistant.notSpecialist`.
 *
 * The inbox (R14, F5): a specialist reads the questions handed to them, `assistant.manage` every one
 * of the firm, anyone else is 403 `assistant.notWriter` — the knowledge base's own writers, since
 * "Virar nota" turns a question into a note. Only the assigned specialist answers (anyone else 404),
 * once (422 `assistant.answered`), and the answer rings the asker alone.
 */
class QuestionService
{
    public function __construct(
        private AssistantQuestionRepository $questionRepository,
        private CurrentFirm $currentFirm,
        private TeamNotifier $teamNotifier,
        private AssistantNotePolicy $notePolicy,
    ) {}

    /** @return Collection<int, AssistantQuestion> */
    public function inbox(User $user): Collection
    {
        if (! $this->notePolicy->write($user)) {
            throw AssistantException::notWriter();
        }

        return $this->questionRepository->findInboxQuestions($this->notePolicy->manage($user) ? null : $user->id);
    }

    /** A question of the person's inbox, or 404 `assistant.questionNotFound` — "Virar nota"'s lookup. */
    public function findInInbox(User $user, int $id): AssistantQuestion
    {
        $question = $this->questionRepository->findQuestionById($id);

        if (! $question || (! $this->notePolicy->manage($user) && $question->specialist_id !== $user->id)) {
            throw AssistantException::questionNotFound();
        }

        return $question;
    }

    public function answer(User $specialist, int $id, string $answer): AssistantQuestion
    {
        $question = $this->questionRepository->findQuestionById($id);

        if (! $question || $question->specialist_id !== $specialist->id) {
            throw AssistantException::questionNotFound();
        }

        if ($question->status !== QuestionStatus::Aberta->value) {
            throw AssistantException::answered();
        }

        return DB::transaction(function () use ($question, $specialist, $answer) {
            if (! $this->questionRepository->claimAnswer($question, $answer, CarbonImmutable::now())) {
                throw AssistantException::answered();
            }

            $question->refresh()->load(['asker', 'specialist']);

            if ($question->asker) {
                $this->teamNotifier->notifyUser($question->asker, new AssistantAnswerNotification(
                    questionId: $question->id,
                    question: $question->question,
                    answeredBy: trim($specialist->name.' '.$specialist->last_name),
                ));
            }

            return $question;
        });
    }

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

            return $created->setRelation('specialist', $specialist)->setRelation('asker', $asker);
        });
    }
}
