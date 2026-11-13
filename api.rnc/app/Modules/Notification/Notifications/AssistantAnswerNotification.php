<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "resposta do especialista" (rnc-3 R14): the specialist a question was handed to answered
 * it. Rings the member of the team who asked, alone.
 */
class AssistantAnswerNotification extends Notification
{
    public const KIND = 'assistant_answer';

    public function __construct(
        public readonly int $questionId,
        public readonly string $question,
        public readonly string $answeredBy,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, question_id: int, question: string, answered_by: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => self::KIND,
            'question_id' => $this->questionId,
            'question' => $this->question,
            'answered_by' => $this->answeredBy,
        ];
    }
}
