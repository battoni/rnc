<?php

namespace App\Modules\Lead\Helpers;

/**
 * The words of the partner-introduction checkbox (rnc-1 R11, plan AM12) — the demo's
 * `consentText(tenant)` for Franco. A change of wording is a new VERSION: every stored answer keeps
 * the text the person actually read.
 *
 * Under a sub-brand (rnc-3 R3) the sentence names the brand the visitor is on — "Autorizo a Franco
 * Prime Contabilidade a me apresentar…" — while the network stays Franco's: the brands share it (D1).
 * The main brand's words are `TEXT`, unchanged, so the version does not move.
 */
final class ConsentTerms
{
    public const VERSION = 1;

    public const TEXT = 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

    /** The channel of an answer given in the diagnostic's form. */
    public const VIA_FORM = 'formulario';

    private const BRAND_TEXT = 'Autorizo a %s a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

    /** @param  string|null  $brandName  the sub-brand's full name; null is the main brand */
    public static function textFor(?string $brandName): string
    {
        return $brandName === null ? self::TEXT : sprintf(self::BRAND_TEXT, $brandName);
    }
}
