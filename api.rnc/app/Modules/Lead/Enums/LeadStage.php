<?php

namespace App\Modules\Lead\Enums;

/**
 * The pipeline's stages (rnc-1 R18, plan AM13). The diagnostic moves a lead only forward along its
 * own steps: `visitante` at step 1 (no personal data), `lead_parcial` with the contact, `lead` when
 * the result is seen; the booking (F4) makes it `reuniao_marcada` from any of those three. `proposta`, `cliente` and `perdido`
 * are the team's (F5).
 */
enum LeadStage: string
{
    case Visitante = 'visitante';
    case LeadParcial = 'lead_parcial';
    case Lead = 'lead';
    case ReuniaoMarcada = 'reuniao_marcada';
    case Proposta = 'proposta';
    case Cliente = 'cliente';
    case Perdido = 'perdido';

    /** @return list<self> where the visitor's own diagnostic may take a lead from this stage */
    public function allowedDiagnosticTransitions(): array
    {
        return match ($this) {
            self::Visitante => [self::LeadParcial],
            self::LeadParcial => [self::Lead],
            default => [],
        };
    }

    public function canAdvanceByDiagnosticTo(self $next): bool
    {
        return in_array($next, $this->allowedDiagnosticTransitions(), true);
    }

    /** The visitor books a meeting only once, from a stage the team has not taken over (AM13). */
    public function canBeBooked(): bool
    {
        return ! $this->locksProfile();
    }

    /** From the booked meeting on, the visitor can no longer change the answers (AM3). */
    public function locksProfile(): bool
    {
        return ! in_array($this, [self::Visitante, self::LeadParcial, self::Lead], true);
    }
}
