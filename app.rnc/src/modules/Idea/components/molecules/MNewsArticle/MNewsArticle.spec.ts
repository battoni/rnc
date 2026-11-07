import { describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() }),
}));

import MNewsArticle from './MNewsArticle.vue';

const ENTRY = {
  id: 21,
  title: 'Uma entrada',
  subtitle: null,
  body: '<p>corpo</p>',
  link: 'https://example.com/changelog',
  areas: [],
  published: true,
  publishedAt: '2026-09-01T12:00:00Z',
  createdAt: '2026-09-01T12:00:00Z',
  cover: null,
};

describe('MNewsArticle', () => {
  /*
   * The link is text someone reads, and the primary ramp does not flip: primary-700 on the light
   * canvas measured 1.93:1. It takes the heading role and says it is a link with its underline.
   */
  it('draws the entry link in a text role, underlined, never in a primary shade', () => {
    const wrapper = mountWithPlugins(MNewsArticle, {
      props: { entry: ENTRY },
      global: { stubs: { MNewsReactions: true } },
    });

    const classes = wrapper.get('[data-testid="news-article-link"]').classes();

    expect(classes.some((name) => name.startsWith('text-primary-'))).toBe(false);
    expect(classes).toContain('text-heading');
    expect(classes).toContain('underline');
  });

  /*
   * The sanitiser strips `target` and `rel` on write, so a link in the body navigated away from the
   * SPA in the same tab. They are put back at render time, always as a pair — and a stored `target`
   * without its `rel` never survives.
   */
  it('opens every link in the body in a new tab, with its rel', () => {
    const wrapper = mountWithPlugins(MNewsArticle, {
      props: {
        entry: {
          ...ENTRY,
          body: '<p><a href="https://example.com/a">a</a> e <a href="https://example.com/b" target="_self">b</a></p>',
        },
      },
      global: { stubs: { MNewsReactions: true } },
    });

    const links = wrapper.get('[data-testid="news-article-body"]').findAll('a');

    expect(links).toHaveLength(2);

    links.forEach((link) => {
      expect(link.attributes('target')).toBe('_blank');
      expect(link.attributes('rel')).toBe('noopener noreferrer');
    });
  });
});
