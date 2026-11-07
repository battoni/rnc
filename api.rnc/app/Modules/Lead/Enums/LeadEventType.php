<?php

namespace App\Modules\Lead\Enums;

/** The lines of a lead's timeline (rnc-1 R19): the diagnostic's and the booking's, then the team's (F5) and the referrals (F6). */
enum LeadEventType: string
{
    case DiagnosticStarted = 'diagnostico.inicio';
    case CnpjLookedUp = 'cnpj.consultado';
    case ContactCaptured = 'contato.capturado';
    case StepReached = 'diagnostico.passo';
    case ResultSeen = 'resultado.visto';
    case MeetingBooked = 'reuniao.marcada';
    case StageChanged = 'etapa.alterada';
    case NoteAdded = 'nota.adicionada';
    case ReferralSent = 'encaminhamento.enviado';
}
