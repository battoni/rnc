import { REFERRAL_STATUS } from './enums';

/** The screens this module owns, linked by name (celer-05): the rail and Home's redirect link here. */
const PORTAL_ROUTE_NAMES = { home: 'portal', statement: 'portal-statement' } as const;

/** The partner's only key (rnc-2 R1, `RoleSeeder::PARCEIRO_KEYS`). The operator holds it too. */
const PORTAL_PERMISSIONS = { access: 'portal.access' } as const;

/**
 * The role of a partner's login. The rail gates the portal entry on it as well as on the key, so the
 * operator — who holds the key — never sees the portal link (A4).
 */
const PORTAL_ROLE_SLUG = 'parceiro';

/** The back office's own key: every staff role holds it, a partner never does. */
const STAFF_HOME_PERMISSION = 'home.read';

/** The first-contact target in business hours (`ReferralSla::HOURS`, rnc-2 R8). */
const SLA_HOURS = 24;

/** The statuses between the accept and the closing — the inbox's "Em andamento" (the demo's `partnerInbox`). */
const IN_PROGRESS_STATUSES: readonly REFERRAL_STATUS[] = [
  REFERRAL_STATUS.ACEITO,
  REFERRAL_STATUS.CONTATO,
  REFERRAL_STATUS.REUNIAO,
  REFERRAL_STATUS.PROPOSTA,
];

/** The moves that ask for something first, in a form (R7): the meeting's date-time, the contract's value. */
const MOVES_WITH_FORM: readonly REFERRAL_STATUS[] = [REFERRAL_STATUS.REUNIAO, REFERRAL_STATUS.FECHADO];

/** The refusals a move names, by the API's stable `code` (`ReferralException`) — never by its sentence. */
const TRANSITION_ERROR_KEYS: Record<string, string> = {
  invalid_transition: 'portal.referrals.invalidTransition',
};

/** The typo guard on a contract value (`TransitionReferralRequest::DEAL_VALUE_MAX_CENTS`, R$ 1 bilhão). */
const DEAL_VALUE_MAX_CENTS = 100_000_000_000;

export {
  DEAL_VALUE_MAX_CENTS,
  IN_PROGRESS_STATUSES,
  MOVES_WITH_FORM,
  PORTAL_PERMISSIONS,
  PORTAL_ROLE_SLUG,
  PORTAL_ROUTE_NAMES,
  SLA_HOURS,
  STAFF_HOME_PERMISSION,
  TRANSITION_ERROR_KEYS,
};
