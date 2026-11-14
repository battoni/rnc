import type { RouteRecordRaw } from 'vue-router';

/*
 * One landing per rail group (Equipe · Reinaldo · Operador). `meta.permission` closes each route
 * with the same key that hides its rail entry (`WORKSPACES`); `meta.workspace` tells the shared view
 * which one it is. The screens of later phases arrive as their own routes and rail children; the
 * team's landing redirects to its pipeline (F5), Reinaldo's to his daily briefing (rnc-3 F9, R26 —
 * it was his metrics since rnc-1 F7).
 */
const WORKSPACE_VIEW = () => import('./WorkspaceView.view.vue');

const WorkspaceRoutes: RouteRecordRaw[] = [
  // The team's landing IS its pipeline (F5): the rail's Equipe link and the home card arrive on the board.
  { path: '/team', name: 'team-home-en', redirect: '/team/pipeline' },
  { path: '/equipe', name: 'team-home-pt-BR', redirect: '/equipe/pipeline' },
  // Reinaldo's landing IS his briefing (rnc-3 F9, R26): the rail's Reinaldo link and the home card arrive on it.
  { path: '/management', name: 'management-home-en', redirect: '/management/briefing' },
  { path: '/gestao', name: 'management-home-pt-BR', redirect: '/gestao/briefing' },
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
