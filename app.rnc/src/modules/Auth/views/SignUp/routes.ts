import type { RouteRecordRaw } from 'vue-router';

export default <RouteRecordRaw[]>[
  {
    path: '/onboarding',
    name: 'auth.onboarding',
    meta: { isPublic: true },
    component: () => import('./SignUpView.vue'),
  },
  {
    path: '/completar-cadastro',
    name: 'auth.completarCadastro',
    meta: { isPublic: true },
    component: () => import('./SignUpView.vue'),
  },
];
