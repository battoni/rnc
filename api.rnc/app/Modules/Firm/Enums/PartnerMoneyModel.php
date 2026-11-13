<?php

namespace App\Modules\Firm\Enums;

/**
 * How a firm's network pays it (rnc-2 R11, D10): one model for every partner. `comissao` — a share
 * of each closing's first-year value; `assinatura` — a flat fee a month for each partner with
 * portal access, closings add nothing. `firms.partner_money_model` holds the value.
 */
enum PartnerMoneyModel: string
{
    case Comissao = 'comissao';
    case Assinatura = 'assinatura';

    /** The model Franco compares against on the network ledger. */
    public function other(): self
    {
        return $this === self::Comissao ? self::Assinatura : self::Comissao;
    }
}
