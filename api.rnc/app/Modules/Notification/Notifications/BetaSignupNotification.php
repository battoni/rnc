<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "inscrição no beta" (rnc-4 R29, D29): a firm signed up for the platform's beta on the
 * root site and is now a `beta` lead of the platform firm. Rings the platform firm's team only.
 * Database channel only; the SPA writes the sentence from `kind` and the fields.
 */
class BetaSignupNotification extends Notification
{
    public const KIND = 'beta_signup';

    public function __construct(
        public readonly int $leadId,
        public readonly string $company,
        public readonly string $city,
        public readonly string $uf,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, lead_id: int, company: string, city: string, uf: string} */
    public function toArray(object $notifiable): array
    {
        return ['kind' => self::KIND, 'lead_id' => $this->leadId, 'company' => $this->company, 'city' => $this->city, 'uf' => $this->uf];
    }
}
