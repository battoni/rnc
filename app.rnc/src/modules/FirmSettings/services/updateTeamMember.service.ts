import type { TeamMemberUpdatePayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import type { User } from '@UserModule';
import { API } from '@Providers';

/** An edit of a team login, or a password alone (the reset, `users.reset_password`). */
export default async function updateTeamMember(
  id: number,
  payload: TeamMemberUpdatePayload
): Promise<ApiResponse<User>> {
  return API({ endpoint: `auth/users/${id}`, forwardError: true, method: 'patch', payload });
}
