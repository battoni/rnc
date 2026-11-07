<?php

namespace App\Modules\Partner\Enums;

/**
 * Where a referral stands (the demo's `REFERRAL_STATUSES`). Phase 1 only writes `enviado`; the
 * partner portal of Phase 2 moves it through the rest.
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
}
