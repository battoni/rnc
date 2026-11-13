<?php

namespace App\Modules\Fiscal\Enums;

/** A document seen from the client it was matched to (R15, D6): emitted by it, or addressed to it. */
enum NfeDirection: string
{
    case Saida = 'saida';
    case Entrada = 'entrada';
}
