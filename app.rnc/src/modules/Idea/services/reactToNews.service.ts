import type { NEWS_REACTION } from '../enums';
import type { NewsEntry } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Applause. Unlimited on purpose — the server counts rather than toggles. */
export default async function reactToNews(id: number, type: NEWS_REACTION): Promise<ApiResponse<NewsEntry>> {
  return API({ endpoint: `news/${id}/reactions`, method: 'post', payload: { type } });
}
