<?php

namespace App\Modules\Lead\Helpers;

/**
 * The words of the partner-introduction checkbox (rnc-1 R11, plan AM12) — the demo's
 * `consentText(tenant)` for Franco. A change of wording is a new VERSION: every stored answer keeps
 * the text the person actually read.
 */
final class ConsentTerms
{
    public const VERSION = 1;

    public const TEXT = 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

    /** The channel of an answer given in the diagnostic's form. */
    public const VIA_FORM = 'formulario';
}
