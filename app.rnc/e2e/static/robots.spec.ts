import { expect, test } from '@playwright/test';

/*
 * Not a flow, and here anyway: this is the only gate in app.rnc that speaks HTTP, and the
 * property worth asserting is not that `public/robots.txt` EXISTS — it is that a crawler
 * asking for `/robots.txt` gets it.
 *
 * The difference is not theoretical. codelumen has an edge gate whose matcher is `/:path*`,
 * so a request for its `/robots.txt` is answered with a redirect to GitHub's OAuth screen;
 * a file placed there would be unreachable and would state nothing to anyone. docs.rnc's
 * matcher excludes `.txt` precisely so its file IS served while the pages stay gated. Reading
 * the file off disk cannot tell those two situations apart. A request can.
 */

test.describe('robots.txt', () => {
  test('is served at the root path', async ({ request }) => {
    const response = await request.get('/robots.txt');

    expect(response.status()).toBe(200);
    expect(await response.text()).toContain('Disallow: /');
  });

  test('disallows every path, rather than allowing every path', async ({ request }) => {
    const body = await (await request.get('/robots.txt')).text();

    // `Disallow:` with nothing after it does not mean "block" — an empty Disallow means ALLOW
    // EVERYTHING. It is the one directive whose failure mode is the exact inverse of what it
    // looks like, and the bootstrap shipped it that way in api.rnc once.
    //
    // Anchored on a positive assertion first: a `not.toMatch` alone also passes against a page
    // that is not robots.txt at all, which is what a missing file serves here.
    expect(body).toMatch(/^Disallow: \/$/m);
    expect(body).not.toMatch(/^Disallow:\s*$/m);
  });

  test('names the AI crawlers individually as well as by wildcard', async ({ request }) => {
    const body = await (await request.get('/robots.txt')).text();

    // Several operators honour only their own token and treat `User-agent: *` as not
    // addressed to them, so the wildcard alone is not the same request.
    expect(body).toContain('User-agent: *');

    for (const crawler of ['GPTBot', 'ClaudeBot', 'Google-Extended', 'Bytespider', 'PerplexityBot']) {
      expect(body).toContain(crawler);
    }
  });

  test('refuses training in every user-agent group, not just the first', async ({ request }) => {
    const body = await (await request.get('/robots.txt')).text();

    // `Content-Signal` states what may be DONE with the content, which `Disallow` does not
    // cover. It is not redundant: Claude-User, ChatGPT-User and Perplexity-User are
    // USER-TRIGGERED fetches, documented by their operators as NOT bound by robots.txt
    // exclusion — somebody pasted a link and the agent goes to read it. For those three this
    // line is the only statement that reaches them, and the only one of app.rnc's three layers
    // that says anything about TRAINING at all; `noindex` speaks about indexing.
    const signals = body.match(/^Content-Signal:.+$/gm) ?? [];

    // One per group. A signal in the wildcard group alone is silently ignored by every
    // operator that reads only its own token — the same trap as the wildcard Disallow.
    const groups = (body.match(/^User-agent: \*$/gm) ?? []).length + Number(body.includes('User-agent: ClaudeBot'));

    // Anchor the count before comparing it. `signals.length === groups` is trivially true when
    // BOTH are zero, and zero is exactly what a missing file produces: the dev server answers
    // /robots.txt with the SPA's index.html fallback — status 200, no directives at all. This
    // assertion passed against that page until a mutation run caught it.
    expect(groups).toBeGreaterThan(1);
    expect(signals).toHaveLength(groups);

    for (const signal of signals) {
      expect(signal).toContain('ai-train=no');
      expect(signal).toContain('ai-input=no');
      expect(signal).toContain('search=no');
    }
  });
});
