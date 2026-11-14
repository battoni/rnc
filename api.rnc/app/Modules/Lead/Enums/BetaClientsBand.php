<?php

namespace App\Modules\Lead\Enums;

/** How many clients a firm signing up for the beta serves (rnc-4 R29) — the form's "carteira" band. */
enum BetaClientsBand: string
{
    case Ate100 = 'ate_100';
    case De101a300 = 'de_101_a_300';
    case De301a1000 = 'de_301_a_1000';
    case Acima1000 = 'acima_1000';
}
