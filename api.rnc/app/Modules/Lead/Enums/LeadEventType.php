<?php

namespace App\Modules\Lead\Enums;

use Illuminate\Support\Arr;

/** The lines of a lead's timeline (rnc-1 R19): the diagnostic's and the booking's, then the team's (F5), the referrals (F6) and the partner's moves (rnc-2 R9). */
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
    case ReferralStatusChanged = 'encaminhamento.status';
    case Anonymised = 'dados.anonimizados';

    /**
     * This line's `data` once the lead is anonymised (rnc-4 R39, verify P2-A6): what the team wrote
     * about the person (a note, a lost reason) goes, the line stays. Every case is listed — no
     * default — so a new line type is a decision about its personal data, not a silent keep.
     * The other lines carry no data of the lead's: a path, a step, counts, a consent flag, the
     * specialist's display name and time, the partner's name and the referral's moves.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withoutPersonalData(array $data): array
    {
        return match ($this) {
            self::NoteAdded => [...$data, 'text' => null],
            self::StageChanged => Arr::except($data, 'reason'),
            self::DiagnosticStarted, self::CnpjLookedUp, self::ContactCaptured, self::StepReached, self::ResultSeen,
            self::MeetingBooked, self::ReferralSent, self::ReferralStatusChanged, self::Anonymised => $data,
        };
    }
}
