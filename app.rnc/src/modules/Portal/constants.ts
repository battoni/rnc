/** The screens this module owns, linked by name (celer-05): the rail and the Home redirect link here. */
const PORTAL_ROUTE_NAMES = { home: 'portal' } as const;

/** The partner's only key (rnc-2 R1, `RoleSeeder::PARCEIRO_KEYS`). The operator holds it too. */
const PORTAL_PERMISSIONS = { access: 'portal.access' } as const;

/**
 * The role of a partner's login. The rail gates the portal entry on it as well as on the key, so
 * the operator — who holds every key — never sees a portal link (A4).
 */
const PORTAL_ROLE_SLUG = 'parceiro';

/** The back office's own key: every staff role holds it, a partner never does. */
const STAFF_HOME_PERMISSION = 'home.read';

export { PORTAL_PERMISSIONS, PORTAL_ROLE_SLUG, PORTAL_ROUTE_NAMES, STAFF_HOME_PERMISSION };
