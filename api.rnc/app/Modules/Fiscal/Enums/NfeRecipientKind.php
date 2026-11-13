<?php

namespace App\Modules\Fiscal\Enums;

/** Who an NF-e is addressed to (R16): a company by its CNPJ, or a consumer — a CPF or no `dest` at all. */
enum NfeRecipientKind: string
{
    case Cnpj = 'cnpj';
    case Consumidor = 'consumidor';
}
