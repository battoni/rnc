import Button from 'primevue/button';
import Editor from 'primevue/editor';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import { describe, expect, it } from 'vitest';
import type { NewsEntry } from '../../../interfaces';
import MImageUpload from '@/components/molecules/MImageUpload/MImageUpload.vue';
import { mountWithPlugins } from '@/test/mount';
import MNewsForm from './MNewsForm.vue';

/*
 * The app auto-imports these children; the unit-test harness does not, so an unregistered child
 * renders as an unknown element and every assertion about its output passes over nothing.
 *
 * Text is asserted in ENGLISH: the harness boots i18n on `VITE_DEFAULT_LANGUAGE`, which is `en`.
 */
const GLOBAL_COMPONENTS = { Button, Editor, InputText, MImageUpload, MultiSelect, Select, ToggleSwitch };

function makeEntry(overrides: Partial<NewsEntry> = {}): NewsEntry {
  return {
    id: 11,
    title: 'The report now exports CSV',
    subtitle: null,
    body: '<p>Pronto.</p>',
    link: null,
    areas: [],
    published: true,
    publishedAt: '2026-08-28T10:00:00+00:00',
    createdAt: '2026-08-28T10:00:00+00:00',
    cover: null,
    idea: null,
    reactions: { clap: 0, heart: 0, rocket: 0 },
    myReactions: { clap: 0, heart: 0, rocket: 0 },
    read: true,
    ...overrides,
  };
}

function mount(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MNewsForm, {
    props,
    global: { components: GLOBAL_COMPONENTS },
  });
}

function submitLabel(wrapper: ReturnType<typeof mount>) {
  return wrapper.find('[data-testid="news-submit"]').text();
}

describe('MNewsForm — what the submit button promises', () => {
  /*
   * The switch and the button are the same decision shown twice, and they disagreed: with the
   * switch on Draft the button still read "Publish". The one that decides is the switch.
   */
  it('offers to save a draft while the switch says draft', async () => {
    const WRAPPER = mount();

    await WRAPPER.findComponent(ToggleSwitch).setValue(false);

    expect(submitLabel(WRAPPER)).toBe('Save draft');
  });

  it('offers to publish a new entry the switch is going to publish', () => {
    expect(submitLabel(mount())).toBe('Publish');
  });

  it('offers to save an entry that already exists', () => {
    expect(submitLabel(mount({ entry: makeEntry() }))).toBe('Save');
  });

  it('offers to save a draft when an entry is being pulled back to one', async () => {
    const WRAPPER = mount({ entry: makeEntry() });

    await WRAPPER.findComponent(ToggleSwitch).setValue(false);

    expect(submitLabel(WRAPPER)).toBe('Save draft');
  });
});
