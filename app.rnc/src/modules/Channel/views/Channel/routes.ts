import type { RouteRecordRaw } from 'vue-router';
import { CHANNEL_PERMISSIONS, CHANNEL_ROUTE_NAMES } from '../../constants';

/*
 * The "Canal RNC" screen (rnc-3 R8, R9) under two groups: the operator's, who runs it, and
 * Reinaldo's, who reads it. One view; what it shows follows `channel.manage`, never the address.
 */
const ChannelRoutes: RouteRecordRaw[] = [
  {
    path: '/operator/rnc',
    name: `${CHANNEL_ROUTE_NAMES.operator}-en`,
    meta: { permission: CHANNEL_PERMISSIONS.read },
    component: () => import('./ChannelView.view.vue'),
  },
  {
    path: '/operador/canal-rnc',
    name: `${CHANNEL_ROUTE_NAMES.operator}-pt-BR`,
    meta: { permission: CHANNEL_PERMISSIONS.read },
    component: () => import('./ChannelView.view.vue'),
  },
  {
    path: '/ceo/rnc',
    name: `${CHANNEL_ROUTE_NAMES.ceo}-en`,
    meta: { permission: CHANNEL_PERMISSIONS.read },
    component: () => import('./ChannelView.view.vue'),
  },
  {
    path: '/reinaldo/canal-rnc',
    name: `${CHANNEL_ROUTE_NAMES.ceo}-pt-BR`,
    meta: { permission: CHANNEL_PERMISSIONS.read },
    component: () => import('./ChannelView.view.vue'),
  },
];

export default ChannelRoutes;
