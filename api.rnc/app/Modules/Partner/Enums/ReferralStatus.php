<?php

namespace App\Modules\Partner\Enums;

/**
 * Where a referral stands (the demo's `REFERRAL_STATUSES`). Franco writes `enviado`; the partner
 * moves it from its portal along the demo's graph (rnc-2 R7, D4) — forward only, one step at a time,
 * and `fechado` and `recusado` end it. Franco reads the status, it never moves it.
 */
enum ReferralStatus: string
{
    case Enviado = 'enviado';
    case Aceito = 'aceito';
    case Contato = 'contato';
    case Reuniao = 'reuniao';
    case Proposta = 'proposta';
    case Fechado = 'fechado';
    case Recusado = 'recusado';

    /** @return list<self> where a partner may take the referral from here */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Enviado => [self::Aceito, self::Recusado],
            self::Aceito => [self::Contato],
            self::Contato => [self::Reuniao],
            self::Reuniao => [self::Proposta],
            self::Proposta => [self::Fechado],
            self::Fechado, self::Recusado => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** The demo's words (pt-BR), for the mail and the logs; the SPA writes its own from the value. */
    public function label(): string
    {
        return match ($this) {
            self::Enviado => 'Novo',
            self::Aceito => 'Aceito',
            self::Contato => 'Em contato',
            self::Reuniao => 'Reunião',
            self::Proposta => 'Proposta',
            self::Fechado => 'Fechado',
            self::Recusado => 'Recusado',
        };
    }

    /** From here on the partner has agreed to call, so it sees the lead's contact (D3). */
    public function showsContact(): bool
    {
        return $this !== self::Enviado && $this !== self::Recusado;
    }
}
