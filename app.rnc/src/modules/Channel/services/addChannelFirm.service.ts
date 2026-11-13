import type { ChannelBoard, ChannelFirmPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new RNC firm, always at contato; answers the whole board (W41). A refusal comes back by field. */
export default async function addChannelFirm(payload: ChannelFirmPayload): Promise<ApiResponse<ChannelBoard>> {
  return API({ endpoint: 'channel/firms', forwardError: true, method: 'post', payload });
}
