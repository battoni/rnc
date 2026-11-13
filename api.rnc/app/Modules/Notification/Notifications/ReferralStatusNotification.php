<?php

namespace App\Modules\Notification\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The bell's "Parceiro aceitou / recusou / fechou contrato" (rnc-2 R9): a partner moved a referral
 * to `aceito`, `recusado` or `fechado`. Data, not sentences — the SPA writes the line from `status`;
 * a closing carries its first-year contract value in cents.
 */
class ReferralStatusNotification extends Notification
{
    public const KIND = 'referral_status';

    public function __construct(
        public readonly int $leadId,
        public readonly int $referralId,
        public readonly string $company,
        public readonly string $partner,
        public readonly string $status,
        public readonly ?int $dealValueCents = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, lead_id: int, referral_id: int, company: string, partner: string, status: string, deal_value_cents?: int} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => self::KIND,
            'lead_id' => $this->leadId,
            'referral_id' => $this->referralId,
            'company' => $this->company,
            'partner' => $this->partner,
            'status' => $this->status,
            ...($this->dealValueCents !== null ? ['deal_value_cents' => $this->dealValueCents] : []),
        ];
    }
}
