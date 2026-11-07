import { describe, expect, it } from 'vitest';
import browserconfig from '../../public/favicon/browserconfig.xml?raw';
import manifest from '../../public/favicon/manifest.json?raw';
import vercelConfig from '../../vercel.json?raw';

/*
 * Every icon the web manifest and the Windows tile config name has to exist at that path.
 *
 * The generator wrote root paths (`/android-icon-36x36.png`) while the files live under
 * `/favicon/`. Nothing reports it: the SPA rewrite answers the missing PNG with `index.html` and a
 * 200, and the only symptom is a blank icon when someone installs the app or pins the tile.
 */

const PUBLIC_FILES = new Set(
  Object.keys(import.meta.glob('../../public/favicon/*', { eager: false })).map((path) =>
    path.replace('../../public', '')
  )
);

function referencedPaths(): string[] {
  const MANIFEST_SOURCES = (JSON.parse(manifest).icons as { src: string }[]).map((icon) => icon.src);
  const TILE_SOURCES = [...browserconfig.matchAll(/src="([^"]+)"/g)].map((match) => match[1]!);

  return [...MANIFEST_SOURCES, ...TILE_SOURCES];
}

describe('favicon manifests', () => {
  it('reference icons that exist, at the path they are served from', () => {
    const PATHS = referencedPaths();

    // Anchor first: an empty list would pass the check below without checking anything.
    expect(PATHS.length).toBeGreaterThan(5);
    expect(PATHS.filter((path) => !PUBLIC_FILES.has(path))).toEqual([]);
  });

  /*
   * The browser bar follows the SCHEME; the installed app and the Windows tile get one colour each.
   *
   * `index.html` declares a `theme-color` per scheme, mirroring `--color-canvas` in the active
   * theme — a bar painted for the wrong scheme is the one surface the theme cannot reach, and on a
   * phone it is half of what somebody sees. The manifest and the tile have no media query to offer,
   * so they take the dark canvas, which is also what `msapplication-TileColor` announces. Three
   * files, one number: this test is what keeps them from drifting apart.
   *
   * It replaces an assertion against a single un-scoped `theme-color` tag — that tag is gone, and
   * with it the brand green the icon generator had written into all three.
   */
  it('agree on one colour for the installed app and the tile, and follow the scheme in the browser bar', async () => {
    const { default: indexHtml } = await import('../../index.html?raw');

    const LIGHT = indexHtml.match(
      /<meta name="theme-color" media="\(prefers-color-scheme: light\)" content="([^"]+)">/
    )?.[1];
    const DARK = indexHtml.match(
      /<meta name="theme-color" media="\(prefers-color-scheme: dark\)" content="([^"]+)">/
    )?.[1];
    const TILE = indexHtml.match(/<meta name="msapplication-TileColor" content="([^"]+)">/)?.[1];

    expect(LIGHT).toMatch(/^#[0-9a-f]{6}$/i);
    expect(DARK).toMatch(/^#[0-9a-f]{6}$/i);
    expect(LIGHT).not.toBe(DARK);

    expect(TILE).toBe(DARK);
    expect(JSON.parse(manifest).theme_color).toBe(DARK);
    expect(browserconfig).toContain(`<TileColor>${DARK}</TileColor>`);
  });

  /*
   * A relative icon path works only at the root. On `/ideias/42` the browser asks for
   * `/ideias/favicon/…`, the SPA fallback answers with `index.html` and a 200, and the icon is
   * broken exactly on the deep links the fallback exists to serve.
   */
  it('link every icon from index.html by an absolute path', async () => {
    const { default: indexHtml } = await import('../../index.html?raw');

    const ICON_PATHS = [...indexHtml.matchAll(/(?:href|content)="([^"]*favicon\/[^"]*)"/g)].map((match) => match[1]!);

    expect(ICON_PATHS.length).toBeGreaterThan(5);
    expect(ICON_PATHS.filter((path) => !path.startsWith('/favicon/'))).toEqual([]);
  });
});

/*
 * robots.txt asks a crawler not to fetch; the `X-Robots-Tag` header and the robots meta tell one that
 * fetched anyway not to keep what it saw. A URL that is only disallowed can still be indexed from an
 * inbound link. The meta is the layer that survives a host serving `index.html` without the header
 * (a preview, a host whose config was not copied), and `.nginx` says it is there.
 */
describe('index.html indexing directive', () => {
  it('carries the same robots directive the hosts send as a header', async () => {
    const { default: indexHtml } = await import('../../index.html?raw');

    const META = indexHtml.match(/<meta name="robots" content="([^"]+)"/)?.[1];
    const HEADER = (JSON.parse(vercelConfig).headers as { headers: { key: string; value: string }[] }[])
      .flatMap((rule) => rule.headers)
      .find((header) => header.key === 'X-Robots-Tag')?.value;

    expect(HEADER).toContain('noindex');
    expect(META).toBe(HEADER);
  });
});
