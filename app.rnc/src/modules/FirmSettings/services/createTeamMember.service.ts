import type { TeamMemberCreatePayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import type { User } from '@UserModule';
import { API } from '@Providers';

/** A team login in the manager's firm — the API stamps the firm and refuses any other role (W76). */
export default async function createTeamMember(payload: TeamMemberCreatePayload): Promise<ApiResponse<User>> {
  return API({ endpoint: 'auth/users', forwardError: true, method: 'post', payload });
}
