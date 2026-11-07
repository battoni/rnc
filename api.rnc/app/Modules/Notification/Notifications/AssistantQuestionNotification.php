<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "pergunta para especialista" (rnc-3 R13): the assistant had no reviewed note for a
 * question and a member of the team handed it to this specialist. Rings that specialist alone.
 */
class AssistantQuestionNotification extends Notification
{
    public const KIND = 'assistant_question';

    public function __construct(
        public readonly int $questionId,
        public readonly string $question,
        public readonly string $askedBy,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, question_id: int, question: string, asked_by: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => self::KIND,
            'question_id' => $this->questionId,
            'question' => $this->question,
            'asked_by' => $this->askedBy,
        ];
    }
}
