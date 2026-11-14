<?php

namespace App\Modules\Lead\Helpers;

/**
 * The fixed parts of a consent answer (rnc-1 R11, plan AM12): the channels an answer arrives by, the
 * placeholder a firm's text names itself with, and the one generic contract sentence.
 *
 * *Retired 14/11/2026 (rnc-4 F2a, R13):* `VERSION`, `TEXT` and `textFor()` — Franco's own sentence
 * as code. The partner-introduction words are now per firm and versioned in `firm_consent_versions`
 * (`ConsentTextService`), and Franco's version 1 is a seed like any firm's.
 */
final class ConsentTerms
{
    /** Where a firm's text names the firm — or the sub-brand — the visitor met (D12). */
    public const PLACEHOLDER = '{escritorio}';

    /** The channel of an answer given in the diagnostic's form. */
    public const VIA_FORM = 'formulario';

    /** The channel of an answer given to the chat receptionist (rnc-2 R14). */
    public const VIA_CHAT = 'chat';

    /**
     * A portfolio client's answer (rnc-2 R27–R28): given in its service contract with the firm, copied
     * from the client onto its `carteira` lead, then onto each referral.
     */
    public const VIA_CONTRACT = 'contrato';

    /** One generic sentence for every firm, not versioned per firm (rnc-4 D12). */
    public const CONTRACT_TEXT = 'O contrato de serviços autoriza {escritorio} a apresentar o cliente a especialistas parceiros quando o Radar da Reforma indicar.';

    public const CONTRACT_VERSION = 1;

    /** A text with `{escritorio}` replaced by the name the visitor met. */
    public static function fill(string $text, string $name): string
    {
        return str_replace(self::PLACEHOLDER, $name, $text);
    }
}
