import type { ChannelBoard, ChannelFirmPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A firm's edit — name, city, fee, share, notes, never its stage; answers the whole board (W41). */
export default async function updateChannelFirm(
  id: number,
  payload: ChannelFirmPayload
): Promise<ApiResponse<ChannelBoard>> {
  return API({ endpoint: `channel/firms/${id}`, forwardError: true, method: 'patch', payload });
}
