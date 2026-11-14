/** The screen this module owns, linked by name (celer-05): the rail links here. */
const CONTRACTS_ROUTE_NAMES = { alert: 'contract-alert' } as const;

/** The key (rnc-3 R18–R22): the contract alert and everything on it are Reinaldo's (gestao; the operator holds every key). */
const CONTRACTS_PERMISSIONS = { manage: 'contracts.manage' } as const;

/** The transition the chips draw, '27 to '33 (R19): the years a contract may be exposed in. */
const TRANSITION_FIRST_YEAR = 2027;

const TRANSITION_LAST_YEAR = 2033;

/** The exposure bar's floor (plan F7): a tiny exposure is still a visible sliver, never nothing. */
const EXPOSURE_BAR_MIN_PERCENT = 2;

/** The refusals the row says on itself, by the API's stable `code` (`ReferralException`). */
const CONTRACT_REFERRAL_ERROR_KEYS: Record<string, string> = {
  already_referred: 'contracts.row.alreadyReferred',
  consent_required: 'contracts.row.consentRequired',
  partner_not_legal: 'contracts.partnerNotLegal',
};

/** The CSV the import reads (rnc-3 R18), in the API's order (`ContractCsvParser::COLUMNS`). */
const CONTRACT_IMPORT_COLUMNS = ['cnpj_cliente', 'tipo', 'valor_anual', 'inicio', 'fim', 'clausula_reforma'] as const;

const CONTRACT_IMPORT_ACCEPT = '.csv,.txt,text/csv,text/plain';

const CONTRACT_TEMPLATE_FILENAME = 'modelo-contratos.csv';

export {
  CONTRACT_IMPORT_ACCEPT,
  CONTRACT_IMPORT_COLUMNS,
  CONTRACT_REFERRAL_ERROR_KEYS,
  CONTRACT_TEMPLATE_FILENAME,
  CONTRACTS_PERMISSIONS,
  CONTRACTS_ROUTE_NAMES,
  EXPOSURE_BAR_MIN_PERCENT,
  TRANSITION_FIRST_YEAR,
  TRANSITION_LAST_YEAR,
};
