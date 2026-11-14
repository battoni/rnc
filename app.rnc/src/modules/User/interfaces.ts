import type { UserStatus } from './types';
import type { Status } from '@Types';

interface CreateUserPayload {
  name: string;
  last_name: string;
  username: string;
  role_id: number;
  status: UserStatus;
  password: string;
  permissions?: Record<string, boolean>;
}

interface GetUsersParams {
  ids?: number[];
  order_by?: string;
  page?: number;
  per_page?: number;
  profile_type?: string;
}

interface UpdateUserPayload {
  name?: string;
  last_name?: string;
  username?: string;
  role_id?: number;
  status?: UserStatus;
  password?: string | null;
  permissions?: Record<string, boolean>;
}

interface User {
  id: number;
  name: string;
  last_name: string;
  username: string;
  role: string;
  role_slug?: string;
  /** The firm whose hub this person works in (rnc-1). */
  firm_slug?: string | null;
  /** A team member a visitor can book (rnc-1 R2). */
  is_specialist?: boolean;
  status?: Status | string;
  permissions?: string[];
  profile_picture?: string;
  email?: string | null;
  email_verified?: boolean;
  phone?: string | null;
  profile_type?: string | null;
  /**
   * The person's firm (rnc-4 R20, api `FirmSummaryResource`): sent by `auth/me` and the sign-in
   * answers only; null for a firm-less account (the platform operator). A list's user has no key.
   */
  firm?: UserFirm | null;
  /** The product and the operator flag (rnc-4 R20); same answers as `firm`. */
  platform?: UserPlatform;
}

/** The signed-in person's firm as the back office's shell boots from it (rnc-4 R20). */
interface UserFirm {
  brand_accent?: string;
  brand_primary?: string;
  is_platform?: boolean;
  logo_text?: string;
  logo_url?: string | null;
  /** The firm's opt-in modules (`FIRM_MODULE`); core is always on and never listed (W34). */
  modules: string[];
  name: string;
  short_name?: string | null;
  slug: string;
}

interface UserPlatform {
  is_operator: boolean;
  name: string;
  /** The firm the operator is viewing as (F4c); null outside it. */
  view_as: { name: string; slug: string } | null;
}

export type { CreateUserPayload, GetUsersParams, UpdateUserPayload, User, UserFirm, UserPlatform };
