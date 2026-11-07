<?php

namespace App\Modules\Diagnostic\Enums;

/** The quiz's tax regimes (rnc-1 R10). `nao_sei` answers the quiz and matches no regime rule. */
enum Regime: string
{
    case Simples = 'simples';
    case Presumido = 'presumido';
    case Real = 'real';
    case NaoSei = 'nao_sei';
}
