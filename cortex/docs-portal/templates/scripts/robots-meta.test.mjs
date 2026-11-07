import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

// robots.txt asks a crawler not to fetch; the X-Robots-Tag header (vercel.json) and this meta tell
// one that fetched anyway not to keep the page. The meta is the layer that travels with the HTML:
// a host without the headers (a preview, another server) still serves it.
test('every page carries a noindex robots meta, matching the X-Robots-Tag header', async () => {
  const { default: config } = await import('../.vitepress/config.mts');
  const robots = (config.head ?? []).find(
    ([tag, attributes]) => tag === 'meta' && attributes?.name === 'robots',
  );
  assert.ok(robots, 'config.head has no <meta name="robots">');

  const vercel = JSON.parse(await readFile(new URL('../vercel.json', import.meta.url), 'utf8'));
  const header = vercel.headers
    .flatMap((rule) => rule.headers)
    .find((entry) => entry.key === 'X-Robots-Tag');
  assert.equal(robots[1].content, header.value);
});
