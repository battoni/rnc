import type { RouteRecordRaw } from 'vue-router';
import { LOGIN_FLOW } from '@Constants';

// prettier-ignore
const loginComponent =
  LOGIN_FLOW === 'phone' ? () => import('./LoginPhoneFlowView.vue')
    : LOGIN_FLOW === 'password' ? () => import('./LoginPasswordFlowView.vue')
    : LOGIN_FLOW === 'username' ? () => import('./LoginUsernamePasswordFlowView.vue')
    : () => import('./LoginUsernamePasswordFlowView.vue');

export default <RouteRecordRaw[]>[
  {
    path: '/login',
    name: 'auth.login',
    meta: { isPublic: true },
    component: loginComponent,
  },
  {
    path: '/entrar',
    name: 'auth.entrar',
    meta: { isPublic: true },
    component: loginComponent,
  },
  {
    path: '/login-email',
    name: 'auth.loginEmail',
    meta: { isPublic: true },
    component: () => import('./LoginUsernameFlowView.vue'),
  },
  {
    path: '/entrar-email',
    name: 'auth.entrarEmail',
    meta: { isPublic: true },
    component: () => import('./LoginUsernameFlowView.vue'),
  },
  {
    path: '/login-confirmation',
    name: 'auth.loginCode',
    meta: { isPublic: true },
    component: () => import('./LoginCodeView.vue'),
  },
  {
    path: '/confirmar-usuario',
    name: 'auth.entrarCodigo',
    meta: { isPublic: true },
    component: () => import('./LoginCodeView.vue'),
  },
];
