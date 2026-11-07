<?php

namespace App\Modules\Assistant\Enums;

/** A handed-off question (rnc-3 R13, R14): aberta until its specialist answers it (F5), then respondida. */
enum QuestionStatus: string
{
    case Aberta = 'aberta';
    case Respondida = 'respondida';
}
