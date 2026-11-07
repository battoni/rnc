import type { RouteRecordRaw } from 'vue-router';
import { PortalInboxRoutes, PortalStatementRoutes } from './views';

/* The partner's portal (rnc-2): signed in with `portal.access`. The inbox (F3) and the statement (F4). */
const PortalModuleRoutes: RouteRecordRaw[] = [...PortalInboxRoutes, ...PortalStatementRoutes];

export { PortalModuleRoutes };
