import type { RouteRecordRaw } from 'vue-router';

export default <RouteRecordRaw[]>[
  {
    path: '/support',
    name: 'auth.support',
    meta: { isPublic: true },
    component: () => import('./SupportView.vue'),
  },
  {
    path: '/suporte',
    name: 'auth.suporte',
    meta: { isPublic: true },
    component: () => import('./SupportView.vue'),
  },
  {
    path: '/forgot-password',
    name: 'auth.forgotPassword',
    meta: { isPublic: true },
    component: () => import('./ForgotPassword.view.vue'),
  },
  {
    path: '/esqueci-minha-senha',
    name: 'auth.esqueceuSenha',
    meta: { isPublic: true },
    component: () => import('./ForgotPassword.view.vue'),
  },
  {
    path: '/recuperar-senha',
    name: 'auth.recuperarSenha',
    meta: { isPublic: true },
    component: () => import('./RecoverAccessView.vue'),
  },
  {
    path: '/reset-password',
    name: 'auth.resetPassword',
    meta: { isPublic: true },
    component: () => import('./ResetPasswordCodeView.vue'),
  },
];
