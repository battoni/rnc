<?php

namespace App\Modules\Booking\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Address;

/**
 * What the confirmation and the reminder print, gathered when they are queued (plan AM6): plain
 * values, no model, so a queue worker — which has no current firm — never runs a firm-scoped query
 * to render them. `startsAt` is already in São Paulo time.
 *
 * `firmName` is the name the visitor met: the lead's sub-brand when it has one (rnc-3 R3), else the
 * firm's. `firmMailbox` is always the firm's own — the brands share Franco's mailbox (D1).
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
        public readonly ?string $firmMailbox = null,
    ) {}

    /** The sender: the brand's name at the firm's mailbox; null keeps `mail.from` (no mailbox). */
    public function sender(): ?Address
    {
        return $this->firmMailbox === null ? null : new Address($this->firmMailbox, $this->firmName);
    }

    /** "terça-feira, 24/11 às 14:30" — the e-mails are pt-BR (AM15). */
    public function when(): string
    {
        return $this->startsAt->locale(self::LOCALE)->translatedFormat('l, d/m \\à\\s H:i');
    }
}
