import type { ChannelBoard, ChannelStage } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * One stage forward, naming the stage the button showed (C3): a stale screen or a second click is
 * 422 `channel.invalidTransition` with `code: invalid_transition`. Answers the whole board (W41).
 */
export default async function advanceChannelFirm(id: number, to: ChannelStage): Promise<ApiResponse<ChannelBoard>> {
  return API({ endpoint: `channel/firms/${id}/advance`, forwardError: true, method: 'post', payload: { to } });
}
