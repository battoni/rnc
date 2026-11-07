import type { RouteRecordRaw } from 'vue-router';

export default <RouteRecordRaw[]>[
  {
    path: '/',
    name: 'home',
    component: () => import('./HomeView.view.vue'),
  },
];
