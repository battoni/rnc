<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "revisão vence em 7 dias" (rnc-3 R11, AM19): a note this specialist reviewed is served
 * only until `expires_on` (a São Paulo day). Rings the reviewer alone, once per review.
 */
class AssistantNoteExpiringNotification extends Notification
{
    public const KIND = 'assistant_note_expiring';

    public function __construct(
        public readonly int $noteId,
        public readonly string $question,
        public readonly string $expiresOn,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, note_id: int, question: string, expires_on: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => self::KIND,
            'note_id' => $this->noteId,
            'question' => $this->question,
            'expires_on' => $this->expiresOn,
        ];
    }
}
