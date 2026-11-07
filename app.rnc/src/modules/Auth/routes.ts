import type { RouteRecordRaw } from 'vue-router';
import {
  ForgotPasswordRoutes,
  LoginRoutes,
  RolesAndPermissionsRoutes,
  SignUpRoutes,
  TermsRoutes,
  VerifyEmailRoutes,
} from './views';

export default <RouteRecordRaw[]>[
  ...ForgotPasswordRoutes,
  ...LoginRoutes,
  ...SignUpRoutes,
  ...TermsRoutes,
  ...RolesAndPermissionsRoutes,
  ...VerifyEmailRoutes,
];
