import type { RouteRecordRaw } from 'vue-router';
import { PortalHomeRoutes } from './views';

/* The partner's portal (rnc-2): signed in with `portal.access`. The inbox and statement follow (F3, F4). */
const PortalModuleRoutes: RouteRecordRaw[] = [...PortalHomeRoutes];

export { PortalModuleRoutes };
