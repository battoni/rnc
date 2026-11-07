import type { RouteRecordRaw } from 'vue-router';

export default <RouteRecordRaw[]>[
  {
    path: '/verify-email',
    name: 'auth.verifyEmail',
    meta: { isPublic: true },
    component: () => import('./VerifyEmailView.vue'),
  },
  {
    path: '/verificar-email',
    name: 'auth.verificarEmail',
    meta: { isPublic: true },
    component: () => import('./VerifyEmailView.vue'),
  },
];
