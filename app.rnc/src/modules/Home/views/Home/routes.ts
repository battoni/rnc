import type { RouteRecordRaw } from 'vue-router';

/*
 * The back office's landing (rnc-1). It left `/` to the public site (AM15) and keeps the literal
 * name `home`: the guards and the login screens send a signed-in person `{ name: 'home' }`.
 *
 * No `meta.permission`, on purpose: the guard sends a refused navigation HERE, so a key on this
 * route would loop for anyone without it. The cards it shows are filtered by permission instead.
 */
export default <RouteRecordRaw[]>[
  {
    path: '/painel',
    name: 'home',
    component: () => import('./HomeView.view.vue'),
  },
];
