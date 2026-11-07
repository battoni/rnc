<?php

namespace App\Modules\Lead\Enums;

/** The lines of a lead's timeline the diagnostic writes (rnc-1 R19). F5 adds the team's. */
enum LeadEventType: string
{
    case DiagnosticStarted = 'diagnostico.inicio';
    case CnpjLookedUp = 'cnpj.consultado';
    case ContactCaptured = 'contato.capturado';
    case StepReached = 'diagnostico.passo';
    case ResultSeen = 'resultado.visto';
}
