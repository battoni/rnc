<?php

namespace App\Modules\Assistant\Services;

use App\Models\User;
use App\Modules\Assistant\DTOs\NoteDTO;
use App\Modules\Assistant\DTOs\UpdateNoteDTO;
use App\Modules\Assistant\Enums\NoteStatus;
use App\Modules\Assistant\Exceptions\AssistantException;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Policies\AssistantNotePolicy;
use App\Modules\Assistant\Repositories\AssistantNoteRepository;
use App\Modules\Assistant\Repositories\AssistantQuestionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Base da Reforma" (rnc-3 R10, R11, D7, D8): the firm's notes, written by `assistant.manage` or a
 * specialist, reviewed by a specialist who did not write the last change, retired by
 * `assistant.manage` (the route's key). A content change sends a reviewed note back to rascunho and
 * clears its review (W53); an aposentada note is neither edited nor reviewed (W55). "Virar nota"
 * (R14, F5) creates the rascunho from a question of the writer's inbox and ties the question to it,
 * once (422 `assistant.alreadyNoted`).
 */
class NoteService
{
    private const SLUG_MAX_LENGTH = 70;

    private const KEYWORD_FIELDS = ['keywords_strong', 'keywords_weak'];

    public function __construct(
        private AssistantNoteRepository $noteRepository,
        private AssistantNotePolicy $notePolicy,
        private QuestionService $questionService,
        private AssistantQuestionRepository $questionRepository,
    ) {}

    /** @return Collection<int, AssistantNote> */
    public function list(User $user): Collection
    {
        $this->ensureWriter($user);

        return $this->noteRepository->findAllNotes();
    }

    /** "N notas vencem em 7 dias" (AM19): read with the list, so held to the same writers. */
    public function expiringCount(): int
    {
        return $this->noteRepository->countExpiringNotes(CarbonImmutable::now());
    }

    public function create(User $user, NoteDTO $note): AssistantNote
    {
        $this->ensureWriter($user);
        $question = $note->from_question === null ? null : $this->questionService->findInInbox($user, $note->from_question);

        return DB::transaction(function () use ($user, $note, $question) {
            $created = $this->noteRepository->createNote([
                ...$this->clean(Arr::except($note->toArray(), 'from_question')),
                'slug' => $this->freeSlug($note->question),
                'status' => NoteStatus::Rascunho->value,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'last_edited_by' => $user->id,
            ]);

            if ($question && ! $this->questionRepository->claimQuestionNote($question, $created->id)) {
                throw AssistantException::alreadyNoted();
            }

            return $created->load(['reviewer', 'lastEditor']);
        });
    }

    /** @return AssistantNote|null null for an unknown or another firm's note */
    public function update(User $user, int $id, UpdateNoteDTO $changes): ?AssistantNote
    {
        $this->ensureWriter($user);
        $note = $this->noteRepository->findNoteById($id);

        if (! $note) {
            return null;
        }

        $this->ensureNotRetired($note);
        $changed = $this->changedContent($note, $this->clean($changes->toArray()));

        if ($changed !== []) {
            $this->noteRepository->updateNote($note, [
                ...$changed,
                'status' => NoteStatus::Rascunho->value,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'last_edited_by' => $user->id,
            ]);
        }

        return $note->load(['reviewer', 'lastEditor']);
    }

    /** @return AssistantNote|null null for an unknown or another firm's note */
    public function review(User $user, int $id): ?AssistantNote
    {
        $note = $this->noteRepository->findNoteById($id);

        if (! $note) {
            return null;
        }

        if (! $this->notePolicy->review($user, $note)) {
            throw AssistantException::notReviewer();
        }

        $this->ensureNotRetired($note);
        $this->noteRepository->updateNote($note, [
            'status' => NoteStatus::Revisada->value,
            'reviewer_id' => $user->id,
            'reviewed_at' => CarbonImmutable::now(),
        ]);

        return $note->load(['reviewer', 'lastEditor']);
    }

    /** @return AssistantNote|null null for an unknown or another firm's note; retiring twice is a no-op */
    public function retire(int $id): ?AssistantNote
    {
        $note = $this->noteRepository->findNoteById($id);

        if (! $note) {
            return null;
        }

        if (NoteStatus::from($note->status)->canTransitionTo(NoteStatus::Aposentada)) {
            $this->noteRepository->updateNote($note, ['status' => NoteStatus::Aposentada->value]);
        }

        return $note->load(['reviewer', 'lastEditor']);
    }

    private function ensureWriter(User $user): void
    {
        if (! $this->notePolicy->write($user)) {
            throw AssistantException::notWriter();
        }
    }

    private function ensureNotRetired(AssistantNote $note): void
    {
        if (NoteStatus::from($note->status)->isTerminal()) {
            throw AssistantException::retired();
        }
    }

    /**
     * Trimmed text and keys, the keys without repeats — so "the same note sent again" is no change.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function clean(array $fields): array
    {
        foreach ($fields as $field => $value) {
            if (in_array($field, self::KEYWORD_FIELDS, true)) {
                $fields[$field] = array_values(array_unique(array_map(fn (string $key) => trim($key), (array) $value)));
            } elseif (is_string($value)) {
                $fields[$field] = trim($value);
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed> the fields whose value differs from the note's
     */
    private function changedContent(AssistantNote $note, array $fields): array
    {
        return array_filter($fields, function (mixed $value, string $field) use ($note) {
            $current = match ($field) {
                'as_of' => $note->as_of->toDateString(),
                default => $note->getAttribute($field),
            };

            return $current !== $value;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /** The question's slug, made free within the firm with `-2`, `-3`… (W57). */
    private function freeSlug(string $question): string
    {
        $base = Str::limit(Str::slug($question), self::SLUG_MAX_LENGTH, '') ?: 'nota';
        $slug = $base;

        for ($suffix = 2; $this->noteRepository->slugExists($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
