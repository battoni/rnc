<?php

namespace App\Modules\Lead\Enums;

/**
 * Where the visitor is in the diagnostic (rnc-1 R11) — the "parou em" the team sees on a lead
 * parcial. The demo's four steps with the contact between the first two.
 */
enum LeadStep: string
{
    case Empresa = 'empresa';
    case Contato = 'contato';
    case Perfil = 'perfil';
    case Faturamento = 'faturamento';
    case Resultado = 'resultado';
}
