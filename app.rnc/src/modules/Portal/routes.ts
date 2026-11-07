import type { RouteRecordRaw } from 'vue-router';
import { PortalInboxRoutes } from './views';

/* The partner's portal (rnc-2): signed in with `portal.access`. The inbox (F3); the statement follows (F4). */
const PortalModuleRoutes: RouteRecordRaw[] = [...PortalInboxRoutes];

export { PortalModuleRoutes };
