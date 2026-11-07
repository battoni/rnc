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

    /** The channel of an answer given to the chat receptionist (rnc-2 R14). */
    public const VIA_CHAT = 'chat';

    /**
     * A portfolio client's answer (rnc-2 R27–R28): given in its service contract with the firm, copied
     * from the client onto its `carteira` lead, then onto each referral.
     */
    public const VIA_CONTRACT = 'contrato';

    public const CONTRACT_TEXT = 'O contrato de serviços autoriza a Franco a apresentar o cliente a especialistas da Rede de Especialistas Franco quando o Radar da Reforma indicar.';
}
