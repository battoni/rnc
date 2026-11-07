import type { NewsEntry } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Opening the article is what marks it read — not scrolling past the card, not landing on the page.
 * A dot that clears itself because you walked past it stops meaning anything.
 */
export default async function markNewsRead(id: number): Promise<ApiResponse<NewsEntry>> {
  return API({ endpoint: `news/${id}/read`, method: 'post' });
}
