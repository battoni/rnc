import type { Component } from 'vue';
import { describe, expect, it } from 'vitest';
import { AEmptyState } from '@/components/atoms/AEmptyState';
import { mountWithPlugins } from '@/test/mount';
import OListAccentStrip from './OListAccentStrip/OListAccentStrip.vue';
import OListCardGrid from './OListCardGrid/OListCardGrid.vue';
import OListDivided from './OListDivided/OListDivided.vue';
import OListFeed from './OListFeed/OListFeed.vue';
import OListStatement from './OListStatement/OListStatement.vue';

/*
 * The row actions every list organism draws: pencil, inbox, bin and eye, each a 28px icon-only
 * button. Two things were true of all of them but one variant of `OListDivided`:
 *
 * - under a finger they stayed 28px, with no growth (they are not `size="small"`, so
 *   `touch-target.css` never reaches them), 4px apart — and the bin is the later sibling, so it
 *   wins the overlap (celer-06);
 * - they had no accessible name, so a screen reader announced four unnamed buttons.
 *
 * jsdom cannot evaluate `pointer: coarse`, so what is asserted is that each button asks for 44px
 * under a finger and that the strip holding it asks for 12px between neighbours.
 */

const COARSE_SIZE = ['pointer-coarse:h-11', 'pointer-coarse:w-11'];

const ACTION_LABELS: Record<string, string> = {
  'pi pi-eye': 'Details',
  'pi pi-inbox': 'Archive',
  'pi pi-pencil': 'Edit',
  'pi pi-trash': 'Delete',
};

const buttonStub = {
  inheritAttrs: false,
  props: ['icon', 'class'],
  emits: ['click'],
  template:
    '<button class="button-stub" :class="$props.class" :data-icon="icon" :aria-label="$attrs[\'aria-label\']" />',
};

const ORGANISMS: [string, Component][] = [
  ['OListAccentStrip', OListAccentStrip],
  ['OListCardGrid', OListCardGrid],
  ['OListDivided', OListDivided],
  ['OListFeed', OListFeed],
  ['OListStatement', OListStatement],
];

function mountList(organism: Component) {
  return mountWithPlugins(organism, {
    props: {
      canArchive: true,
      canDelete: true,
      canEdit: true,
      items: [{ id: '1', name: 'Alice' }],
      showDetails: true,
      title: { valueKey: 'name' },
    },
    global: {
      components: { AEmptyState },
      stubs: {
        Button: buttonStub,
        MMainDialog: true,
        Menu: true,
        Popover: true,
        Tag: true,
      },
    },
  });
}

describe.each(ORGANISMS)('%s row actions', (_name, organism) => {
  it('name every icon-only action for a screen reader', () => {
    const buttons = mountList(organism).findAll('.button-stub');

    expect(buttons.length).toBeGreaterThanOrEqual(4);

    for (const button of buttons) {
      const expected = ACTION_LABELS[button.attributes('data-icon') ?? ''];

      if (!expected) continue;

      expect(button.attributes('aria-label'), button.attributes('data-icon')).toBe(expected);
    }
  });

  it('grow to 44px under a finger, 12px apart', () => {
    const buttons = mountList(organism)
      .findAll('.button-stub')
      .filter((button) => button.classes().includes('h-7'));

    expect(buttons.length).toBeGreaterThanOrEqual(4);

    for (const button of buttons) {
      expect(button.classes(), button.attributes('data-icon')).toEqual(expect.arrayContaining(COARSE_SIZE));
      expect(button.element.parentElement!.classList, button.attributes('data-icon')).toContain('gap-3');
    }
  });
});
