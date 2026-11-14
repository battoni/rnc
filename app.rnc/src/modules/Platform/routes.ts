import type { RouteRecordRaw } from 'vue-router';
import { PLATFORM_PERMISSIONS, PLATFORM_ROUTE_NAMES } from './constants';

/*
 * The platform console (rnc-4 R24, R25): signed in, by permission — keys only the firm-less operator
 * holds (`RoleService` refuses them to any other role, W53). A core surface: no `meta.module`.
 */
const firmsView = () => import('./views/Firms/FirmsView.view.vue');
const consentView = () => import('./views/ConsentDefault/ConsentDefaultView.view.vue');
const viewAsLogView = () => import('./views/ViewAsLog/ViewAsLogView.view.vue');

const PlatformModuleRoutes: RouteRecordRaw[] = [
  {
    path: '/platform/firms',
    name: `${PLATFORM_ROUTE_NAMES.firms}-en`,
    meta: { permission: PLATFORM_PERMISSIONS.firms },
    component: firmsView,
  },
  {
    path: '/plataforma/escritorios',
    name: `${PLATFORM_ROUTE_NAMES.firms}-pt-BR`,
    meta: { permission: PLATFORM_PERMISSIONS.firms },
    component: firmsView,
  },
  {
    path: '/platform/consent',
    name: `${PLATFORM_ROUTE_NAMES.consent}-en`,
    meta: { permission: PLATFORM_PERMISSIONS.consent },
    component: consentView,
  },
  {
    path: '/plataforma/consentimento',
    name: `${PLATFORM_ROUTE_NAMES.consent}-pt-BR`,
    meta: { permission: PLATFORM_PERMISSIONS.consent },
    component: consentView,
  },
  // The view-as log (rnc-4 R26): the console's, on the same key as the firms (W84).
  {
    path: '/platform/view-as-log',
    name: `${PLATFORM_ROUTE_NAMES.viewAsLog}-en`,
    meta: { permission: PLATFORM_PERMISSIONS.firms },
    component: viewAsLogView,
  },

  {
    path: '/plataforma/acessos',
    name: `${PLATFORM_ROUTE_NAMES.viewAsLog}-pt-BR`,
    meta: { permission: PLATFORM_PERMISSIONS.firms },
    component: viewAsLogView,
  },
];

export { PlatformModuleRoutes };
