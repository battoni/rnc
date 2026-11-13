<?php

namespace App\Modules\Metrics\Enums;

use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Tracking\Enums\EventType;

/**
 * The funnel's seven steps, in order (rnc-1 R24; the demo's `FUNNEL`). Each is reached by the site's
 * beacon (Tracking, R7) or by the line the lead's own timeline holds (Lead, R19) — a beacon the
 * browser lost still leaves the line. `cliente` has no beacon: it is the team's move of the lead to
 * `cliente` (an `etapa.alterada` line, F5).
 */
enum FunnelStep: string
{
    case Visitas = 'visitas';
    case Cta = 'cta';
    case Inicio = 'inicio';
    case Contato = 'contato';
    case Resultado = 'resultado';
    case Agendou = 'agendou';
    case Cliente = 'cliente';

    public function beacon(): ?EventType
    {
        return match ($this) {
            self::Visitas => EventType::Visit,
            self::Cta => EventType::Cta,
            self::Inicio => EventType::DiagnosticStart,
            self::Contato => EventType::DiagnosticContact,
            self::Resultado => EventType::DiagnosticResult,
            self::Agendou => EventType::Booking,
            self::Cliente => null,
        };
    }

    /** The timeline line that also proves the step; `cliente`'s is read by its `to`, not by type alone. */
    public function leadEvent(): ?LeadEventType
    {
        return match ($this) {
            self::Inicio => LeadEventType::DiagnosticStarted,
            self::Contato => LeadEventType::ContactCaptured,
            self::Resultado => LeadEventType::ResultSeen,
            self::Agendou => LeadEventType::MeetingBooked,
            self::Cliente => LeadEventType::StageChanged,
            default => null,
        };
    }
}
