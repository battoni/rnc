<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "reunião marcada" (rnc-1 R17): a visitor booked a specialist. `starts_at` is ISO-8601
 * with the São Paulo offset (AM9), as the booking answered it.
 */
class MeetingBookedNotification extends Notification
{
    public const KIND = 'meeting_booked';

    public function __construct(
        public readonly int $leadId,
        public readonly string $company,
        public readonly string $specialist,
        public readonly string $startsAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, lead_id: int, company: string, specialist: string, starts_at: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => self::KIND,
            'lead_id' => $this->leadId,
            'company' => $this->company,
            'specialist' => $this->specialist,
            'starts_at' => $this->startsAt,
        ];
    }
}
