import type { Locator, Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * A headline with nowhere to break.
 *
 * A title is free text, and a 300-character hash has no space and no hyphen — no soft break
 * opportunity at all. Left alone, the line simply does not wrap: measured on this page, the
 * headline's own box is 439px wide and its content 2770px, and the 2331px of overspill paints
 * straight across the card beside it.
 *
 * What makes it worth a test rather than a glance is that NOTHING MOVES. The grid tracks are a
 * fixed width, so no box grows, the document never scrolls sideways, and every layout assertion
 * anyone would reach for first passes with the bug in place — which is exactly what the first
 * version of this file did. The measure that reproduces it is the element against ITSELF: content
 * wider than the box it was given.
 */

const UNBREAKABLE = 'a'.repeat(300);

const NEWS_ENTRY = {
  id: 1,
  title: UNBREAKABLE,
  subtitle: UNBREAKABLE,
  body: `<p>${UNBREAKABLE}</p>`,
  link: null,
  cover: null,
  areas: [],
  published: true,
  publishedAt: '2026-09-01T12:00:00+00:00',
  createdAt: '2026-09-01T12:00:00+00:00',
  idea: null,
  reactions: { clap: 0, heart: 0, rocket: 0 },
  myReactions: { clap: 0, heart: 0, rocket: 0 },
  read: false,
};

function json(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

async function signInAndOpenNews(page: Page) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');

  await page.route('**/auth/me**', (route) =>
    route.fulfill(json({ data: { ...mockUser, permissions: [...mockUser.permissions, 'ideas.read'] } }))
  );
  await page.route('**/news/unread-count**', (route) => route.fulfill(json({ data: { unread: 1 } })));
  await page.route('**/ideas**', (route) => route.fulfill(json({ data: [] })));

  /*
   * The list endpoint, matched without naming `/api` — CI has no `.env`, so `VITE_API_URL` is
   * empty there and the same call goes to `/news` instead of `https://…/api/news`. A pattern that
   * assumed the prefix passed on every machine and failed on the only one that matters.
   *
   * Which is why the document has to be let through by hand: with the prefix gone, the API's list
   * and the page's own URL are the same path, and intercepting the navigation serves JSON as the
   * page.
   */
  await page.route('**/news', (route) =>
    route.request().resourceType() === 'document' ? route.fallback() : route.fulfill(json({ data: [NEWS_ENTRY] }))
  );

  await page.goto('/news');
  await expect(page.getByTestId('news-card-1')).toBeVisible();
}

/** How much of the element's own content does not fit the box it was given. */
async function overspill(target: Locator) {
  return target.evaluate((element) => element.scrollWidth - element.clientWidth);
}

test.describe('a news entry whose text cannot be broken', () => {
  test('wraps the headline and the standfirst inside the card', async ({ page }) => {
    await signInAndOpenNews(page);

    const CARD = page.getByTestId('news-card-1');

    expect(await overspill(CARD.locator('h3'))).toBeLessThanOrEqual(0);
    expect(await overspill(CARD.locator('p').first())).toBeLessThanOrEqual(0);
  });

  test('wraps them again in the opened article, where the body rule never reached', async ({ page }) => {
    await signInAndOpenNews(page);

    await page.getByTestId('news-open').first().click();
    await expect(page.getByTestId('news-article-body')).toBeVisible();

    const ARTICLE = page.getByTestId('news-article-body').locator('..');

    expect(await overspill(ARTICLE.locator('h2'))).toBeLessThanOrEqual(0);
    expect(await overspill(page.getByTestId('news-article-body'))).toBeLessThanOrEqual(0);
  });

  test('holds at phone width, where the column is the screen', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await signInAndOpenNews(page);

    const CARD = page.getByTestId('news-card-1');
    const HORIZONTAL_SCROLL = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(await overspill(CARD.locator('h3'))).toBeLessThanOrEqual(0);
    expect(HORIZONTAL_SCROLL).toBeLessThanOrEqual(0);
  });
});
