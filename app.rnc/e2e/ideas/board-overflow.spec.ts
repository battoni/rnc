import type { Locator, Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * A board of text people wrote, on a phone.
 *
 * An idea's title and description are free text, and nobody keeps them short. A 300-character run
 * with no space and no hyphen has no break opportunity at all, so unless the card allows one the
 * line simply does not wrap. Two measures, because each misses what the other sees: the DOCUMENT
 * scrolling sideways, and an element's content wider than its own box — a fixed grid track keeps
 * the document still while the text paints straight across the card (see `news-overflow.spec.ts`).
 */

test.use({ viewport: { width: 390, height: 844 } });

const UNBREAKABLE = 'a'.repeat(300);

const IDEA = {
  id: 1,
  title: UNBREAKABLE,
  description: UNBREAKABLE,
  type: 'idea',
  area: 'general',
  status: 'received',
  adminNote: null,
  votesCount: 0,
  commentsCount: 0,
  voted: false,
  canEdit: false,
  hidden: false,
  mergedIntoId: null,
  deliveredAt: null,
  createdAt: '2026-09-01T12:00:00+00:00',
  author: { id: 2, name: 'Ana' },
  attachment: null,
};

const OPENED_IDEA = {
  ...IDEA,
  adminNote: UNBREAKABLE,
  commentsCount: 1,
  comments: [
    {
      id: 10,
      ideaId: 1,
      body: UNBREAKABLE,
      isOfficial: false,
      hidden: false,
      deleted: false,
      createdAt: '2026-09-02T12:00:00+00:00',
      author: { id: 3, name: 'Bia' },
    },
  ],
};

function json(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

async function signInAndOpenBoard(page: Page) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');

  await page.route('**/auth/me**', (route) =>
    route.fulfill(json({ data: { ...mockUser, permissions: [...mockUser.permissions, 'ideas.read'] } }))
  );
  await page.route('**/news/unread-count**', (route) => route.fulfill(json({ data: { unread: 0 } })));

  // With `VITE_API_URL` empty (CI has no `.env`), the API's list and this page's own URL are the same
  // path, so the document is let through by hand — see `news-overflow.spec.ts`.
  await page.route('**/ideas**', (route) =>
    route.request().resourceType() === 'document' ? route.fallback() : route.fulfill(json({ data: [IDEA] }))
  );

  await page.goto('/ideas');
  await expect(page.getByTestId('idea-card-1')).toBeVisible();
}

/** How much of the element's own content does not fit the box it was given. */
async function overspill(target: Locator) {
  return target.evaluate((element) => element.scrollWidth - element.clientWidth);
}

test.describe('the Ideas board on a phone', () => {
  // `<= 0`, not `toBe(0)`: with `scrollbar-gutter: stable` the gutter belongs to the client box, so a
  // page that does not scroll sideways measures NEGATIVE, and an equality fails for a reason that is
  // not the bug.
  test('never pushes the page sideways', async ({ page }) => {
    await signInAndOpenBoard(page);

    const HORIZONTAL_SCROLL = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(HORIZONTAL_SCROLL).toBeLessThanOrEqual(0);
  });

  test('keeps an unbreakable title and description inside the card', async ({ page }) => {
    await signInAndOpenBoard(page);

    const CARD = page.getByTestId('idea-card-1');

    expect(await overspill(CARD.getByTestId('idea-open'))).toBeLessThanOrEqual(0);
    expect(await overspill(CARD.locator('p').first())).toBeLessThanOrEqual(0);
  });

  test('keeps them inside the opened idea too, with the answer and a comment', async ({ page }) => {
    await seedAuthSession(page);
    await seedLanguage(page, 'pt-BR');

    await page.route('**/auth/me**', (route) =>
      route.fulfill(json({ data: { ...mockUser, permissions: [...mockUser.permissions, 'ideas.read'] } }))
    );
    await page.route('**/news/unread-count**', (route) => route.fulfill(json({ data: { unread: 0 } })));
    await page.route('**/ideas/1**', (route) =>
      route.request().resourceType() === 'document' ? route.fallback() : route.fulfill(json({ data: OPENED_IDEA }))
    );

    await page.goto('/ideas/1');

    const HEADLINE = page.locator('h2', { hasText: UNBREAKABLE });
    await expect(HEADLINE).toBeVisible();

    const HORIZONTAL_SCROLL = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(HORIZONTAL_SCROLL).toBeLessThanOrEqual(0);
    expect(await overspill(HEADLINE)).toBeLessThanOrEqual(0);

    // Description, the maintainer's answer and the comment body — every paragraph carrying the run.
    const PARAGRAPHS = page.locator('p', { hasText: UNBREAKABLE });
    await expect(PARAGRAPHS).toHaveCount(3);

    for (const paragraph of await PARAGRAPHS.all()) {
      expect(await overspill(paragraph)).toBeLessThanOrEqual(0);
    }
  });
});
