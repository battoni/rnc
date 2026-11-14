/** The screen this module owns, linked by name (celer-05): the rail links here. */
const RADAR_ROUTE_NAMES = { radar: 'portfolio-radar' } as const;

/** The key (rnc-2 D14): the Radar and its batch are Reinaldo's (gestao; the operator holds every key). */
const RADAR_PERMISSIONS = { manage: 'radar.manage' } as const;

/** The rule whose range is cash held per month (R23, R26), drawn with its own line. */
const SPLIT_PAYMENT_RULE = 'split-payment';

/** The batch refusals the row branches on, by the API's stable `code` (`ReferralException`). */
const RADAR_ERROR_KEYS: Record<string, string> = {
  already_referred: 'radar.row.alreadyReferred',
  partner_not_covering: 'radar.row.notCovering',
};

export { RADAR_ERROR_KEYS, RADAR_PERMISSIONS, RADAR_ROUTE_NAMES, SPLIT_PAYMENT_RULE };
