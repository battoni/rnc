<?php

namespace App\Modules\Diagnostic\Enums;

/** How the visitor answers step 1 (rnc-1 R9, R10): the CNPJ lookup or the quiz. */
enum DiagnosticPath: string
{
    case Cnpj = 'cnpj';
    case Quiz = 'quiz';
}
