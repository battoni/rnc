import type { RouteRecordRaw } from 'vue-router';

/*
 * One landing per rail group (Equipe · Reinaldo · Operador). `meta.permission` closes each route
 * with the same key that hides its rail entry (`WORKSPACES`); `meta.workspace` tells the shared view
 * which one it is. The screens of later phases (pipeline, metrics, repricing…) arrive as their own
 * routes and rail children.
 */
const WORKSPACE_VIEW = () => import('./WorkspaceView.view.vue');

const WorkspaceRoutes: RouteRecordRaw[] = [
  {
    path: '/team',
    name: 'team-home-en',
    meta: { permission: 'leads.read', workspace: 'team' },
    component: WORKSPACE_VIEW,
  },
  {
    path: '/equipe',
    name: 'team-home-pt-BR',
    meta: { permission: 'leads.read', workspace: 'team' },
    component: WORKSPACE_VIEW,
  },
  {
    path: '/ceo',
    name: 'ceo-home-en',
    meta: { permission: 'metrics.read', workspace: 'ceo' },
    component: WORKSPACE_VIEW,
  },
  {
    path: '/reinaldo',
    name: 'ceo-home-pt-BR',
    meta: { permission: 'metrics.read', workspace: 'ceo' },
    component: WORKSPACE_VIEW,
  },
  {
    path: '/operator',
    name: 'operator-home-en',
    meta: { permission: 'users.read', workspace: 'operator' },
    component: WORKSPACE_VIEW,
  },
  {
    path: '/operador',
    name: 'operator-home-pt-BR',
    meta: { permission: 'users.read', workspace: 'operator' },
    component: WORKSPACE_VIEW,
  },
];

export default WorkspaceRoutes;
