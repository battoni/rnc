import type { PublicPrivacy } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@Composables';

/** rnc-4 R38: the data the host firm's privacy notice is worded from (`PublicPrivacyResource`). */
export default async function getPublicPrivacy(): Promise<ApiResponse<PublicPrivacy>> {
  return publicApi({
    endpoint: 'public/privacy',
    method: 'get',
  });
}
