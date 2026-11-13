/** The screens this module owns, linked by name (celer-05): the rail links here. */
const METRICS_ROUTE_NAMES = { funnel: 'metrics' } as const;

/** Reinaldo's and the operator's key (plan AM8); the team never holds it. */
const METRICS_PERMISSIONS = { read: 'metrics.read' } as const;

/** The windows the API answers (`FunnelRequest::WINDOWS`); the screen opens on the longer one. */
const METRICS_WINDOWS = [7, 30] as const;

const METRICS_DEFAULT_WINDOW = 30;

export { METRICS_DEFAULT_WINDOW, METRICS_PERMISSIONS, METRICS_ROUTE_NAMES, METRICS_WINDOWS };
