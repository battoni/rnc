import { CLIENT_SECTOR } from '@RepricingModule';
import { READINESS_STATUS } from './enums';

/** The screens this module owns, linked by name (celer-05): the rail links here. */
const FISCAL_ROUTE_NAMES = {
  imports: 'nfe-imports',
  readiness: 'nfe-readiness',
  suppliers: 'supplier-credit',
} as const;

/**
 * The keys (rnc-2 D14): the team and Reinaldo read the two screens — "Registrar aviso" and "Propor
 * revisão" included (plan AM13) —; only Reinaldo imports XMLs.
 */
const FISCAL_PERMISSIONS = { import: 'fiscal.import', read: 'fiscal.read' } as const;

/** The status filter's buttons, in the API's order (`ReadinessStatus::order`, no notes last). */
const READINESS_STATUSES: READINESS_STATUS[] = [
  READINESS_STATUS.CRITICO,
  READINESS_STATUS.ATENCAO,
  READINESS_STATUS.OK,
  READINESS_STATUS.SEM_NOTAS,
];

/** The three statuses a client with notes can have — the bar over the clients drawn on the hero. */
const READINESS_BAR_STATUSES: READINESS_STATUS[] = [
  READINESS_STATUS.CRITICO,
  READINESS_STATUS.ATENCAO,
  READINESS_STATUS.OK,
];

/** The supplier-credit sector filter (api `SupplierCreditIndexRequest`, Diagnostic's `Sector`). */
const FISCAL_SECTORS: CLIENT_SECTOR[] = Object.values(CLIENT_SECTOR);

/** The import's files (api `StoreNfeImportRequest`): one `.xml` or `.zip`, at most 20 MB. */
const IMPORT_ACCEPT = '.xml,.zip,text/xml,application/xml,application/zip';

const IMPORT_EXTENSIONS = ['xml', 'zip'] as const;

const IMPORT_MAX_BYTES = 20 * 1024 * 1024;

/** A page of the import batches. */
const IMPORTS_PER_PAGE = 10;

/** A batch lists this many refusals before "Ver todas". */
const REFUSALS_PREVIEW = 5;

/** The refusals a fiscal action branches on, by the API's stable `code` (`FiscalException`). */
const FISCAL_ERROR_KEYS: Record<string, string> = {
  no_simples_purchases: 'fiscal.suppliers.noSimples',
};

/** A supplier bar shows its percent inside a part from this share up (the demo's 12 %). */
const BAR_LABEL_MIN_SHARE = 12;

/** Below this width the readiness detail sits under the list, so opening a client scrolls to it. */
const DETAIL_BESIDE_QUERY = '(min-width: 96rem)';

export {
  BAR_LABEL_MIN_SHARE,
  DETAIL_BESIDE_QUERY,
  FISCAL_ERROR_KEYS,
  FISCAL_PERMISSIONS,
  FISCAL_ROUTE_NAMES,
  FISCAL_SECTORS,
  IMPORT_ACCEPT,
  IMPORT_EXTENSIONS,
  IMPORT_MAX_BYTES,
  IMPORTS_PER_PAGE,
  READINESS_BAR_STATUSES,
  READINESS_STATUSES,
  REFUSALS_PREVIEW,
};
