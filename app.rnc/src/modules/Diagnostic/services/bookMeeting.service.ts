import type { BookingConfirmation, BookMeetingPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@SiteModule';

/** Books the slot (R16): the lead becomes "Reunião marcada" and the confirmation e-mail goes out. */
export default async function bookMeeting(
  token: string,
  payload: BookMeetingPayload
): Promise<ApiResponse<BookingConfirmation>> {
  return publicApi({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/booking`, method: 'post', payload });
}
