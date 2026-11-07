import { nextTick } from 'vue';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import { describe, expect, it } from 'vitest';
import type { NewsEntry } from '../../../interfaces';
import { mountWithPlugins } from '@/test/mount';
import MNewsReactions from '../MNewsReactions/MNewsReactions.vue';
import MNewsCard from './MNewsCard.vue';

/*
 * The app auto-imports these children (`unplugin-vue-components`); the unit-test harness does not,
 * so an unregistered child renders as an unknown element and every assertion about its output
 * quietly passes over nothing. They are registered by hand here for that reason.
 *
 * Text is asserted in ENGLISH: the harness boots i18n on `VITE_DEFAULT_LANGUAGE`, which is `en`.
 */
const GLOBAL_COMPONENTS = { Button, MNewsReactions, Popover };

const PIXEL_PNG =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

function makeEntry(overrides: Partial<NewsEntry> = {}): NewsEntry {
  return {
    id: 11,
    title: 'The report now exports CSV',
    subtitle: 'The month open, right away, without going through a spreadsheet.',
    body: '<p>Pronto.</p>',
    link: null,
    areas: [],
    published: true,
    publishedAt: '2026-08-28T10:00:00+00:00',
    createdAt: '2026-08-28T10:00:00+00:00',
    cover: { url: PIXEL_PNG, mime: 'image/png', size: 68 },
    idea: null,
    reactions: { clap: 4, heart: 2, rocket: 0 },
    myReactions: { clap: 0, heart: 0, rocket: 0 },
    read: true,
    readCount: 6,
    ...overrides,
  };
}

function mount(props: Record<string, unknown> = {}, attach = false) {
  return mountWithPlugins(MNewsCard, {
    attachTo: attach ? document.body : undefined,
    props: { entry: makeEntry(), ...props },
    global: { components: GLOBAL_COMPONENTS },
  });
}

/**
 * Opens the `⋮` and waits for the teleported panel to mount.
 *
 * The card defers the toggle by two animation frames, so that any scroll it needed in order to
 * bring the card into view has landed before the Popover starts listening for one — a Popover
 * closes itself when an ancestor scrolls, which is what made the menu on a low card shut itself
 * ~350ms after opening. jsdom does not advance frames on its own, so the helper does.
 */
async function open(wrapper: ReturnType<typeof mount>) {
  await wrapper.find('[data-testid="news-actions-11"]').trigger('click');

  await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve(null))));
  await nextTick();
  await nextTick();
}

describe('MNewsCard', () => {
  it('shows the headline, the standfirst and the cover', () => {
    const wrapper = mount();

    expect(wrapper.text()).toContain('The report now exports CSV');
    expect(wrapper.text()).toContain('without going through a spreadsheet');
    expect(wrapper.find('[data-testid="news-cover"]').attributes('src')).toBe(PIXEL_PNG);
  });

  /*
   * A dead cover URL used to render its `alt` — the entry's title — as bare text sprawling outside
   * the card. The alt is empty and the box leaves entirely.
   */
  it('never lets a cover carry the title as text', () => {
    const wrapper = mount();

    expect(wrapper.find('[data-testid="news-cover"]').attributes('alt')).toBe('');
  });

  it('drops the cover box when the image fails to load', async () => {
    const wrapper = mount();

    await wrapper.find('[data-testid="news-cover"]').trigger('error');

    expect(wrapper.find('[data-testid="news-cover"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('The report now exports CSV');
  });

  it('renders no cover box at all when the entry has none', () => {
    const wrapper = mount({ entry: makeEntry({ cover: null }) });

    expect(wrapper.find('[data-testid="news-cover"]').exists()).toBe(false);
  });

  /*
   * The applause is a SIBLING of the card's open button, never a descendant. A `<button>` inside a
   * `<button>` is invalid HTML the browser unnests, and the clap would open the article instead.
   */
  it('keeps the applause outside the button that opens the article', () => {
    const wrapper = mount();

    const openButton = wrapper.find('[data-testid="news-open"]');

    expect(openButton.find('[data-testid="news-react-clap"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="news-react-clap"]').exists()).toBe(true);
  });

  it('disables applause while reactionsDisabled is set', () => {
    const wrapper = mount({ reactionsDisabled: true });

    expect(wrapper.findComponent(MNewsReactions).props('disabled')).toBe(true);
  });

  it('claps without asking to open the article', async () => {
    const wrapper = mount();

    await wrapper.find('[data-testid="news-react-clap"]').trigger('click');

    // The emit forwards the kind AND the settle callback, so the parent can repay this click.
    expect(wrapper.emitted('onReact')?.[0]?.[0]).toBe('clap');
    expect(typeof wrapper.emitted('onReact')?.[0]?.[1]).toBe('function');
    expect(wrapper.emitted('onOpen')).toBeUndefined();
  });

  it('opens the article from the cover and the headline', async () => {
    const wrapper = mount();

    await wrapper.find('[data-testid="news-open"]').trigger('click');

    expect(wrapper.emitted('onOpen')).toHaveLength(1);
  });

  /* A reader gets the changelog and nothing that hints the changelog is editable. */
  it('shows a reader no actions and no audience', () => {
    const wrapper = mount();

    expect(wrapper.find('[data-testid="news-actions-11"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="news-readers-11"]').exists()).toBe(false);
  });

  /*
   * The read count is information, not an action, so it stays on the card while the three write
   * actions move behind the `⋮`.
   */
  it('keeps the read count on the card, beside the menu', () => {
    const wrapper = mount({ canManage: true });

    expect(wrapper.find('[data-testid="news-readers-11"]').text()).toContain('6');
    expect(wrapper.find('[data-testid="news-actions-11"]').exists()).toBe(true);
  });

  /*
   * The menu's rows live in a `Popover`, which teleports to `document.body` and mounts nothing
   * until it is open — so these are queried off the document, not off the wrapper, and the card is
   * attached to the body so the trigger it anchors to is in a real layout.
   */
  it('holds publish, edit and delete in the overflow menu', async () => {
    const wrapper = mount({ canManage: true }, true);

    await open(wrapper);

    expect(document.querySelector('[data-testid="news-publish-11"]')).not.toBeNull();
    expect(document.querySelector('[data-testid="news-edit-11"]')).not.toBeNull();
    expect(document.querySelector('[data-testid="news-delete-11"]')).not.toBeNull();

    wrapper.unmount();
  });

  it('offers to pull a published entry back, and to publish a draft', async () => {
    const published = mount({ canManage: true }, true);
    await open(published);
    expect(document.querySelector('[data-testid="news-publish-11"]')?.textContent).toContain('Unpublish');
    published.unmount();

    const draft = mount({ canManage: true, entry: makeEntry({ published: false, publishedAt: null }) }, true);
    await open(draft);
    expect(document.querySelector('[data-testid="news-publish-11"]')?.textContent).toContain('Publish');
    expect(draft.find('[data-testid="news-draft-badge"]').exists()).toBe(true);
    draft.unmount();
  });

  it('asks the parent to publish from the menu', async () => {
    const wrapper = mount({ canManage: true }, true);

    await open(wrapper);
    document.querySelector<HTMLElement>('[data-testid="news-publish-11"]')?.click();
    await nextTick();

    expect(wrapper.emitted('onPublish')).toHaveLength(1);

    wrapper.unmount();
  });

  /*
   * The delete confirmation anchors to the `⋮` itself, not to the menu row that asked for it: the
   * menu closes on the click, and a `ConfirmPopup` pointed at a removed element lands in the
   * top-left corner of the page.
   */
  /*
   * The anchor is the menu ROW, not the `⋮`. A ConfirmPopup points at the element that asked, and
   * the row is what the person clicked and what stays on screen behind the confirmation — the
   * menu is deliberately left open for exactly that reason.
   */
  it('hands the delete confirmation the row to anchor to', async () => {
    const wrapper = mount({ canManage: true }, true);

    await open(wrapper);
    const row = document.querySelector<HTMLElement>('[data-testid="news-delete-11"]');
    row?.click();
    await nextTick();

    expect(wrapper.emitted('onDelete')?.[0]?.[0]).toBe(row);

    wrapper.unmount();
  });
});
