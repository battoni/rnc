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
}

export type { CreateUserPayload, GetUsersParams, UpdateUserPayload, User };
