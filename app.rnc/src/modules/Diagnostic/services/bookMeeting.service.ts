import type { BookingConfirmation, BookMeetingPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Books the slot (R16): the lead becomes "Reunião marcada" and the confirmation e-mail goes out. */
export default async function bookMeeting(
  token: string,
  payload: BookMeetingPayload
): Promise<ApiResponse<BookingConfirmation>> {
  return API({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/booking`, method: 'post', payload });
}
