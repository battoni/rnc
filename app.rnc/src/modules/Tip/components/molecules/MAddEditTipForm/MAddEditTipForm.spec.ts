import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

const { createTipService, toastAdd, updateTipService } = vi.hoisted(() => ({
  createTipService: vi.fn(),
  toastAdd: vi.fn(),
  updateTipService: vi.fn(),
}));

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

vi.mock('../../../services', () => ({
  CreateTipService: createTipService,
  UpdateTipService: updateTipService,
}));

import MAddEditTipForm from './MAddEditTipForm.vue';

const editorStub = {
  name: 'Editor',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  // Renders the #toolbar slot so the spec can assert which buttons the form actually offers.
  template: `<div class="editor-stub"><slot name="toolbar" /><textarea :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" /></div>`,
};
const inputTextStub = {
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template: `<input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
};
const selectStub = {
  props: ['modelValue', 'options'],
  emits: ['update:modelValue'],
  template: `<select v-bind="$attrs" :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
};

function buildTip(overrides: Record<string, unknown> = {}) {
  return {
    anchor_key: 'users.list',
    archived_at: null,
    content: '<p>Original</p>',
    created_at: '2026-09-01T10:00:00Z',
    created_by: null,
    id: 4,
    updated_at: '2026-09-01T10:00:00Z',
    updated_by: null,
    ...overrides,
  };
}

function mountForm(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MAddEditTipForm, {
    props,
    global: { stubs: { Editor: editorStub, InputText: inputTextStub, Select: selectStub } },
  });
}

describe('MAddEditTipForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('offers only anchors that are still free', () => {
    const wrapper = mountForm({ takenAnchorKeys: ['users.list'] });

    const values = wrapper.findAll('option').map((option) => option.attributes('value'));

    expect(values).not.toContain('users.list');
    expect(values).toContain('roles.permissions');
  });

  it('keeps the tip own anchor selectable while editing it', () => {
    const wrapper = mountForm({ takenAnchorKeys: ['users.list'], tip: buildTip() });

    const values = wrapper.findAll('option').map((option) => option.attributes('value'));

    // Otherwise editing a tip would blank its own anchor and look like a move.
    expect(values).toContain('users.list');
  });

  it('labels each anchor with where it lives', () => {
    const wrapper = mountForm();

    const labels = wrapper.findAll('option').map((option) => option.text());

    expect(labels).toContain('Administration › Users list');
  });

  it('keeps submit disabled until an anchor and some text exist', async () => {
    const wrapper = mountForm();

    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();

    await wrapper.get('select').setValue('roles.permissions');
    await wrapper.get('textarea').setValue('<p>New text</p>');

    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
  });

  it('treats an empty editor document as no text', async () => {
    const wrapper = mountForm();

    await wrapper.get('select').setValue('roles.permissions');
    // Quill emits this for an empty document, so "not an empty string" is not the same question.
    await wrapper.get('textarea').setValue('<p><br></p>');

    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
  });

  it('does not ask for a reason when creating', async () => {
    createTipService.mockResolvedValue({ data: buildTip() });

    const wrapper = mountForm();

    await wrapper.get('select').setValue('roles.permissions');
    await wrapper.get('textarea').setValue('<p>New text</p>');

    expect(wrapper.find('[data-testid="tip-reason"]').exists()).toBe(false);

    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(createTipService).toHaveBeenCalledWith({
      anchor_key: 'roles.permissions',
      change_reason: null,
      content: '<p>New text</p>',
    });
  });

  it('asks for a reason only once the text actually changed', async () => {
    const wrapper = mountForm({ tip: buildTip() });

    expect(wrapper.find('[data-testid="tip-reason"]').exists()).toBe(false);

    await wrapper.get('textarea').setValue('<p>Reescrito</p>');

    expect(wrapper.find('[data-testid="tip-reason"]').exists()).toBe(true);
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
  });

  it('submits the edit with the reason attached', async () => {
    updateTipService.mockResolvedValue({ data: buildTip({ content: '<p>Reescrito</p>' }) });

    const wrapper = mountForm({ tip: buildTip() });

    await wrapper.get('textarea').setValue('<p>Reescrito</p>');
    await wrapper.get('[data-testid="tip-reason"]').setValue('termo errado');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(updateTipService).toHaveBeenCalledWith(4, {
      anchor_key: 'users.list',
      change_reason: 'termo errado',
      content: '<p>Reescrito</p>',
    });
    expect(wrapper.emitted('onSuccess')?.[0]?.[0]).toMatchObject({ content: '<p>Reescrito</p>' });
  });

  it('does not demand a reason to move a tip to another anchor', async () => {
    updateTipService.mockResolvedValue({ data: buildTip({ anchor_key: 'roles.permissions' }) });

    const wrapper = mountForm({ tip: buildTip() });

    await wrapper.get('select').setValue('roles.permissions');

    expect(wrapper.find('[data-testid="tip-reason"]').exists()).toBe(false);

    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(updateTipService).toHaveBeenCalledWith(4, {
      anchor_key: 'roles.permissions',
      change_reason: null,
      content: '<p>Original</p>',
    });
  });

  it('offers no formatting the API would strip away', () => {
    const wrapper = mountForm();

    const toolbarButtons = wrapper.findAll('.ql-formats button').map((button) => button.classes().join(' '));

    // A button that inserts what the sanitiser discards is worse than no button.
    expect(toolbarButtons.some((classes) => classes.includes('ql-image'))).toBe(false);
    expect(toolbarButtons.some((classes) => classes.includes('ql-bold'))).toBe(true);
  });

  describe('when the API refuses the save (422)', () => {
    function refusal(field: string, key: string) {
      return { response: { status: 422, data: { errors: { [field]: [key] } } } };
    }

    async function createWith(wrapper: ReturnType<typeof mountForm>) {
      await wrapper.get('select').setValue('roles.permissions');
      await wrapper.get('textarea').setValue('<p>New text</p>');
      await wrapper.get('form').trigger('submit');
      await flushPromises();
    }

    // The rule goes where the problem is, and no toast contradicts it (celer-07).
    it('shows a taken anchor under the anchor field, with no toast', async () => {
      createTipService.mockRejectedValue(refusal('anchor_key', 'tips.errors.anchorTaken'));
      const wrapper = mountForm();

      await createWith(wrapper);

      expect(wrapper.get('[data-testid="tip-anchor-refusal"]').text()).toBe(
        'There is already an active tip in this place.'
      );
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('shows an empty-text refusal under the editor', async () => {
      createTipService.mockRejectedValue(refusal('content', 'tips.errors.contentRequired'));
      const wrapper = mountForm();

      await createWith(wrapper);

      expect(wrapper.get('[data-testid="tip-content-refusal"]').text()).toBe('The tip text cannot be empty.');
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('shows a missing reason under the reason field', async () => {
      updateTipService.mockRejectedValue(refusal('change_reason', 'tips.errors.reasonRequired'));
      const wrapper = mountForm({ tip: buildTip() });

      await wrapper.get('textarea').setValue('<p>Reescrito</p>');
      await wrapper.get('[data-testid="tip-reason"]').setValue('x');
      await wrapper.get('form').trigger('submit');
      await flushPromises();

      expect(wrapper.get('[data-testid="tip-reason-refusal"]').text()).toBe('A change reason is required.');
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('shows a refusal about any other field under the form', async () => {
      createTipService.mockRejectedValue(refusal('anchor', 'tips.errors.anchorTaken'));
      const wrapper = mountForm();

      await createWith(wrapper);

      expect(wrapper.get('[data-testid="tip-form-refusal"]').text()).toBe(
        'There is already an active tip in this place.'
      );
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('keeps the red toast for a failure that is not a refusal', async () => {
      createTipService.mockRejectedValue({ response: { status: 500 } });
      const wrapper = mountForm();

      await createWith(wrapper);

      expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
      expect(wrapper.find('[data-testid="tip-anchor-refusal"]').exists()).toBe(false);
    });
  });

  // Adjacent controls sit 12px apart at least; at 8px the grown hit areas overlap (VIS-15).
  it('keeps the footer buttons at least gap-3 apart', () => {
    const wrapper = mountForm();

    expect(wrapper.get('button[type="submit"]').element.parentElement?.classList).toContain('gap-3');
  });
});
