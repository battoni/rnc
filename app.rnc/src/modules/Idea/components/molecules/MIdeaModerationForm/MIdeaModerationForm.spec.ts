import { describe, expect, it } from 'vitest';
import type { Idea } from '../../../interfaces';
import { mountWithPlugins } from '@/test/mount';
import MIdeaModerationForm from './MIdeaModerationForm.vue';

const IDEA = { adminNote: null, hidden: false, id: 1, status: 'open' } as unknown as Idea;

const STUBS = {
  Button: { props: ['label'], template: '<button type="button">{{ label }}</button>' },
  Select: true,
  Textarea: true,
};

describe('MIdeaModerationForm', () => {
  // Save, Hide and Merge sit side by side; at 8px their grown touch areas overlap (VIS-15).
  it('keeps its three actions at least gap-3 apart', () => {
    const wrapper = mountWithPlugins(MIdeaModerationForm, { props: { idea: IDEA }, global: { stubs: STUBS } });

    const ACTIONS = wrapper.findAll('button');

    expect(ACTIONS).toHaveLength(3);
    expect(ACTIONS[0]!.element.parentElement?.classList).toContain('gap-3');
  });
});
