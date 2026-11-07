interface ConfirmPasswordResetPayload {
  code: string;
  password: string;
  passwordConfirmation: string;
  token: string;
}

interface ConfirmPasswordResetResponse {
  ok: boolean;
}

/** The shape a refused sign-in arrives in, whichever login form asked. See `useLoginError`. */
interface LoginApiError {
  response?: {
    status?: number;
    data?: {
      message?: string;
      errors?: Record<string, string[]>;
    };
  };
}

/** A refused phone-code request or verify. `code` is what the screen switches on, never `error`. */
interface LoginCodeApiError {
  response?: {
    status?: number;
    data?: {
      code?: string;
      error?: string;
      errors?: Record<string, string[]>;
    };
  };
}

/** What `auth/phone/code` answers. `dev_code` exists only when the API exposes it (local). */
interface LoginCodeRequested {
  dev_code?: string;
  expires_in: number;
  phone: string;
}

interface LoginPasswordCredentialsPayload {
  identifierMode: 'email' | 'phone';
  password: string;
  username: string;
}

/** One switch in a role's permission panel. Same shape as `PermissionGroupItem`, kept for its callers. */
interface Permission {
  key: string;
  labelKey: string;
  value: boolean;
}

interface PermissionGroup {
  id: string;
  /** Sent with a role: the role has a row for one of this group's keys. */
  isHeld?: boolean;
  nameKey: string;
  icon: string;
  permissions: PermissionGroupItem[];
}

interface PermissionGroupItem {
  key: string;
  labelKey: string;
  value: boolean;
}

/** A role as `auth/roles` returns it: the Roles screen, its services and the permission panels. */
interface Profile {
  description?: string;
  id: number;
  name: string;
  permissionGroups: PermissionGroup[];
  slug?: string;
  users: string[];
}

interface RegisterUserPayload {
  // Laravel's `confirmed` rule reads this exact snake_case name; it is the API's spelling,
  // not ours, so it is not renamed on the way out.
  birthDate: string;
  cpf: string;
  email: string;
  firstName: string;
  lastName: string;
  password: string;
  password_confirmation: string;
  phone?: string;
  termsAccepted: boolean;
}

interface VerifyEmailPayload {
  code: string;
  email: string;
}

/**
 * Two shapes in one, on purpose. With e-mail verification on, only `name` and `email` come
 * back and a code is in flight; with it off the account exists and the full user arrives,
 * `id` and all. `id` is how the caller knows which happened.
 */
interface RegisterUserResponse {
  email?: string;
  id?: number;
  name?: string;
  username?: string;
}

interface RequestEmailOtpPayload {
  email?: string;
  phone?: string;
}

interface RequestEmailOtpResponse {
  sent: boolean;
}

interface RequestPasswordResetPayload {
  email?: string;
  phone?: string;
}

interface RequestPasswordResetResponse {
  token: string;
}

interface ResendPasswordResetPayload {
  token: string;
}

interface ResendPasswordResetResponse {
  ok: boolean;
}

interface RequestPhoneCodePayload {
  phone: string;
}

interface VerifyLoginCodePayload {
  code: string;
}

interface VerifyPhoneCodePayload {
  code: string;
  phone: string;
}

interface VerifyLoginCodeResponse {
  email: string;
  id: number;
  name: string;
}

export type {
  ConfirmPasswordResetPayload,
  ConfirmPasswordResetResponse,
  LoginApiError,
  LoginCodeApiError,
  LoginCodeRequested,
  LoginPasswordCredentialsPayload,
  Permission,
  PermissionGroup,
  PermissionGroupItem,
  Profile,
  RegisterUserPayload,
  RegisterUserResponse,
  RequestEmailOtpPayload,
  RequestEmailOtpResponse,
  RequestPasswordResetPayload,
  RequestPhoneCodePayload,
  RequestPasswordResetResponse,
  ResendPasswordResetPayload,
  ResendPasswordResetResponse,
  VerifyEmailPayload,
  VerifyLoginCodePayload,
  VerifyLoginCodeResponse,
  VerifyPhoneCodePayload,
};
