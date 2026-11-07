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
  status?: Status | string;
  permissions?: string[];
  profile_picture?: string;
  email?: string | null;
  email_verified?: boolean;
  phone?: string | null;
  profile_type?: string | null;
}

export type { CreateUserPayload, GetUsersParams, UpdateUserPayload, User };
