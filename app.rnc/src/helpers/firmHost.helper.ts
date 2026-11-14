import { ROOT_DOMAIN } from '@Constants';

/**
 * The firm a host names (rnc-4 R2): `<slug>.<root>` → `slug`; the root, `www.<root>` and any host
 * outside the root (an IP, a preview domain) → `null`, which the API answers as the platform firm.
 *
 * The slug is passed on as the host spells it, lower-cased: whether it exists, or is reserved, is the
 * API's answer (404 `firms.notFound`), never a list kept here.
 */
function firmSlugFromHost(host: string, rootDomain: string): string | null {
  const HOSTNAME = host.toLowerCase().split(':')[0] ?? '';
  const ROOT = rootDomain.toLowerCase();
  const SUFFIX = `.${ROOT}`;

  if (!HOSTNAME.endsWith(SUFFIX)) return null;

  const SLUG = HOSTNAME.slice(0, -SUFFIX.length);

  return SLUG && SLUG !== 'www' ? SLUG : null;
}

/** The firm of the page's own host — what every API call names in `X-Firm`. */
function currentFirmSlug(): string | null {
  return firmSlugFromHost(window.location.hostname, ROOT_DOMAIN);
}

/** The root site, on the scheme and port the page is on: another host, so a link and not a route. */
function rootSiteUrl(location: Pick<Location, 'port' | 'protocol'>, rootDomain: string): string {
  const PORT = location.port ? `:${location.port}` : '';

  return `${location.protocol}//${rootDomain}${PORT}/`;
}

export { currentFirmSlug, firmSlugFromHost, rootSiteUrl };
