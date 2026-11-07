<?php

namespace App\Modules\Booking\DTOs;

use Carbon\CarbonImmutable;

/**
 * What the confirmation and the reminder print, gathered when they are queued (plan AM6): plain
 * values, no model, so a queue worker — which has no current firm — never runs a firm-scoped query
 * to render them. `startsAt` is already in São Paulo time.
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
    ) {}

    /** "terça-feira, 24/11 às 14:30" — the e-mails are pt-BR (AM15). */
    public function when(): string
    {
        return $this->startsAt->locale(self::LOCALE)->translatedFormat('l, d/m \\à\\s H:i');
    }
}
