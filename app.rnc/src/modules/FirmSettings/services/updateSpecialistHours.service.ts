import type { SpecialistHour, SpecialistHoursPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One specialist's whole week; the answer is the stored week in reading order. */
export default async function updateSpecialistHours(
  id: number,
  payload: SpecialistHoursPayload
): Promise<ApiResponse<SpecialistHour[]>> {
  return API({ endpoint: `specialists/${id}/hours`, forwardError: true, method: 'put', payload });
}
