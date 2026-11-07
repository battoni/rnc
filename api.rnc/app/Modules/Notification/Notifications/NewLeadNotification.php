<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "novo lead" (rnc-1 R17): a lead left its contact in the diagnostic for the first time
 * (it is now a lead parcial). Stored on the database channel only; the SPA writes the sentence from
 * `kind` and the fields, so the row holds data, not pt-BR text.
 */
class NewLeadNotification extends Notification
{
    public const KIND = 'new_lead';

    public function __construct(
        public readonly int $leadId,
        public readonly string $company,
        public readonly ?string $sector,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, lead_id: int, company: string, sector: string|null} */
    public function toArray(object $notifiable): array
    {
        return ['kind' => self::KIND, 'lead_id' => $this->leadId, 'company' => $this->company, 'sector' => $this->sector];
    }
}
