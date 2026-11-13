import { CLIENT_REGIME, CLIENT_SECTOR } from './enums';

/** The screens this module owns, linked by name (celer-05): the rail links here. */
const REPRICING_ROUTE_NAMES = { letter: 'repricing-letter', list: 'repricing' } as const;

/** Reinaldo's and the operator's keys (plan AM8); the team holds neither. */
const REPRICING_PERMISSIONS = { import: 'clients.import', manage: 'repricing.manage' } as const;

/** The list's filters, in the order the selects offer them. */
const REPRICING_SECTORS: CLIENT_SECTOR[] = Object.values(CLIENT_SECTOR);

const REPRICING_REGIMES: CLIENT_REGIME[] = Object.values(CLIENT_REGIME);

/**
 * The columns of the import's CSV, in the template's order (api `ClientCsvParser::COLUMNS` and the
 * optional `CONSENT_COLUMN`, rnc-2 R28 — a 7-column file still imports).
 */
const IMPORT_COLUMNS = [
  'nome',
  'cnpj',
  'setor',
  'regime',
  'faixa_faturamento',
  'percentual_b2b',
  'mensalidade',
  'autoriza_parceiros',
] as const;

/** The file the template downloads as, and the files the picker offers. */
const IMPORT_TEMPLATE_FILENAME = 'modelo-clientes.csv';

const IMPORT_ACCEPT = '.csv,text/csv';

/** Below this width the detail sits under the list, so opening a client scrolls to it. */
const DETAIL_BESIDE_QUERY = '(min-width: 96rem)';

export {
  DETAIL_BESIDE_QUERY,
  IMPORT_ACCEPT,
  IMPORT_COLUMNS,
  IMPORT_TEMPLATE_FILENAME,
  REPRICING_PERMISSIONS,
  REPRICING_REGIMES,
  REPRICING_ROUTE_NAMES,
  REPRICING_SECTORS,
};
