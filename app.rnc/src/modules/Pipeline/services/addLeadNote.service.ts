import type { LeadNotePayload, LeadTimelineEvent } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A team note; answers its timeline line. */
export default async function addLeadNote(
  id: number,
  payload: LeadNotePayload
): Promise<ApiResponse<LeadTimelineEvent>> {
  return API({ endpoint: `leads/${id}/notes`, method: 'post', payload });
}
