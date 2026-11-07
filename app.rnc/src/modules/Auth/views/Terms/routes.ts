import type { RouteRecordRaw } from 'vue-router';

export default <RouteRecordRaw[]>[
  {
    path: '/terms',
    name: 'auth.terms',
    meta: { isPublic: true },
    component: () => import('./TermsView.vue'),
  },
  {
    path: '/termos',
    name: 'auth.termos',
    meta: { isPublic: true },
    component: () => import('./TermsView.vue'),
  },
];
