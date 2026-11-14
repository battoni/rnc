<?php

namespace App\Modules\Partner\DTOs;

use App\Modules\Firm\Services\MailBrand;

/**
 * What the partner's e-mail prints (plan AM6): plain values gathered inside the request, so the
 * queued Mailable never queries — a worker has no current firm. It carries the company and Franco's
 * contact, NEVER the lead's (R22: the partner talks to the lead only through Franco).
 */
final class ReferralMailDTO
{
    /** @param  list<string>  $covers  the titles of the diagnostic items the partner can help with */
    public function __construct(
        public readonly string $partnerName,
        public readonly string $specialty,
        public readonly string $company,
        public readonly ?string $sector,
        public readonly array $covers,
        public readonly string $message,
        public readonly string $referredBy,
        public readonly string $firmName,
        public readonly ?string $firmPhone,
        public readonly ?string $firmMailbox,
        public readonly ?string $firmAddress,
        public readonly MailBrand $brand,
    ) {}
}
