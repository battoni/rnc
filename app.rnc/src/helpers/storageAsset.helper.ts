/**
 * The API answers a file on its public disk (a firm's logo, rnc-4 F4c) as `APP_URL/storage/...` — the
 * API's own address, `http://localhost:8000` on a laptop, never the firm's host. The SPA asks for it on
 * its OWN origin instead, where Vite (`vite.config.ts`, locally and in test mode) and the deploy's vhost
 * (plan AM10) serve `/storage` per host. An address outside `/storage` is left as it came; none → null.
 */
const STORAGE_PREFIX = '/storage/';

function toStorageAssetPath(url: string | null | undefined): string | null {
  if (!url) return null;

  if (url.startsWith(STORAGE_PREFIX)) return url;

  const PARSED = URL.canParse(url) ? new URL(url) : null;

  return PARSED?.pathname.startsWith(STORAGE_PREFIX) ? `${PARSED.pathname}${PARSED.search}` : url;
}

export { toStorageAssetPath };
