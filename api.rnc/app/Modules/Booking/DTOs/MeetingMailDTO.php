<?php

namespace App\Modules\Booking\DTOs;

use App\Modules\Firm\Services\MailBrand;
use Carbon\CarbonImmutable;

/**
 * What the confirmation and the reminder print, gathered when they are queued (plan AM6): plain
 * values, no model, so a queue worker — which has no current firm — never runs a firm-scoped query
 * to render them. `startsAt` is already in São Paulo time.
 *
 * `firmName` is the name the visitor met: the lead's sub-brand when it has one (rnc-3 R3), else the
 * firm's. `brand` is the e-mail's look and sender (rnc-4 R12): the sub-brand's name and colours,
 * the firm's mailbox to reply to — the brands share their firm's mailbox (rnc-3 D1).
 */
final class MeetingMailDTO
{
    private const LOCALE = 'pt_BR';

    public function __construct(
        public readonly ?string $firstName,
        public readonly string $company,
        public readonly string $specialist,
        public readonly ?string $area,
        public readonly CarbonImmutable $startsAt,
        public readonly int $minutes,
        public readonly int $opportunities,
        public readonly int $risks,
        public readonly string $reportUrl,
        public readonly string $firmName,
        public readonly ?string $firmAddress,
        public readonly ?string $firmPhone,
        public readonly MailBrand $brand,
    ) {}

    /** "terça-feira, 24/11 às 14:30" — the e-mails are pt-BR (AM15). */
    public function when(): string
    {
        return $this->startsAt->locale(self::LOCALE)->translatedFormat('l, d/m \\à\\s H:i');
    }
}
