<?php

namespace App\Modules\Assistant\Services;

use App\Models\User;
use App\Modules\Assistant\Contracts\ReformaAnswerer;
use App\Modules\Assistant\DTOs\AskDTO;
use App\Modules\Assistant\Helpers\QuestionNormalizer;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Repositories\AssistantAskRepository;
use App\Modules\Assistant\Repositories\AssistantNoteRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * "Assistente da Reforma" (rnc-3 R12, R13, R15, D9): a question goes to the `ReformaAnswerer` — a
 * chip by its note's id — and is logged with the note that answered or none. With no note, the
 * answer is the firm's specialists to hand it to and nothing else: no sentence is generated (W50).
 * Gaps are the last 30 days' asks without a note (R15).
 *
 * @phpstan-type Answer array{question: string, note: AssistantNote|null, specialists: Collection<int, User>}
 */
class AskService
{
    public const GAP_WINDOW_DAYS = 30;

    /** `assistant_asks.normalised`: an accent spelled out in ASCII ("ß" → "ss") can lengthen the text. */
    private const NORMALISED_MAX_LENGTH = 300;

    public function __construct(
        private ReformaAnswerer $answerer,
        private AssistantAskRepository $askRepository,
        private AssistantNoteRepository $noteRepository,
        private QuestionService $questionService,
    ) {}

    /** @return Answer */
    public function ask(User $user, AskDTO $ask): array
    {
        $note = $ask->note_id === null ? $this->answerer->answer($ask->question) : $this->answerer->answerById($ask->note_id);

        $this->askRepository->createAsk([
            'user_id' => $user->id,
            'question' => $ask->question,
            'normalised' => Str::limit(QuestionNormalizer::normalise($ask->question), self::NORMALISED_MAX_LENGTH, ''),
            'note_id' => $note?->id,
            'at' => CarbonImmutable::now(),
        ]);

        return [
            'question' => $ask->question,
            'note' => $note,
            'specialists' => $note ? new Collection : $this->questionService->specialists(),
        ];
    }

    /** @return Collection<int, AssistantNote> the chips: the served notes' questions */
    public function suggestions(): Collection
    {
        return $this->noteRepository->findServedNoteQuestions(CarbonImmutable::now());
    }

    /** @return list<array{normalised: string, question: string, count: int, last_asked_at: CarbonImmutable}> */
    public function gaps(): array
    {
        return $this->askRepository->findAskGapsSince(CarbonImmutable::now()->subDays(self::GAP_WINDOW_DAYS));
    }
}
