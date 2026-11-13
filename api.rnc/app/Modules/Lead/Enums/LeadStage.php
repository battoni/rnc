<?php

namespace App\Modules\Lead\Enums;

/**
 * The pipeline's stages (rnc-1 R18, plan AM13). The diagnostic moves a lead only forward along its
 * own steps: `visitante` at step 1 (no personal data), `lead_parcial` with the contact, `lead` when
 * the result is seen; the booking (F4) makes it `reuniao_marcada` from any of those three. `proposta`, `cliente` and `perdido`
 * are the team's (F5) — and the team moves a lead to ANY other stage, backwards too (plan AM13); only
 * `perdido` asks why.
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

    /**
     * The board's order (R18): the pipeline from the anonymous visitor to the client, then perdido.
     *
     * @return list<self>
     */
    public static function boardOrder(): array
    {
        return self::cases();
    }

    /** The team moves a card anywhere but where it already is (AM13). */
    public function canBeMovedByTeamTo(self $next): bool
    {
        return $next !== $this;
    }

    /** A lead is marked perdido only with the reason it was lost (R18). */
    public function requiresReason(): bool
    {
        return $this === self::Perdido;
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
