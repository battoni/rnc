import type { ChannelBoard } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The "Canal RNC" board: the summary and every firm — with prices only for `channel.manage` (AM15). */
export default async function getChannel(): Promise<ApiResponse<ChannelBoard>> {
  return API({ endpoint: 'channel', method: 'get' });
}
