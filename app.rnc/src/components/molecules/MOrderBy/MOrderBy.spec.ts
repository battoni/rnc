import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MOrderBy from './MOrderBy.vue';

// Named stubs so findComponent(ref) can locate them reliably.
const selectStub = {
  name: 'Select',
  props: ['modelValue', 'options', 'optionLabel', 'optionValue', 'showClear', 'inputId'],
  emits: ['update:modelValue'],
  template: '<div class="select-stub" />',
};

const primevueStubs = {
  InputGroup: { template: '<div class="input-group-stub"><slot /></div>' },
  InputGroupAddon: { template: '<div class="input-group-addon-stub"><slot /></div>' },
  FloatLabel: { template: '<div class="float-label-stub"><slot /></div>' },
  Select: selectStub,
};

const options = [
  { label: 'Name A-Z', value: 'name_asc' },
  { label: 'Name Z-A', value: 'name_desc' },
];

describe('MOrderBy', () => {
  it('renders without errors', () => {
    const wrapper = mountWithPlugins(MOrderBy, {
      props: { options },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.exists()).toBe(true);
  });

  it('renders the sort-by label (i18n key common.orderBy = "Sort by")', () => {
    const wrapper = mountWithPlugins(MOrderBy, {
      props: { options },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.text()).toContain('Sort by');
  });

  it('passes options to the Select stub', () => {
    const wrapper = mountWithPlugins(MOrderBy, {
      props: { options },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.findComponent(selectStub).props('options')).toEqual(options);
  });

  it('passes the current orderBy value to Select', () => {
    const wrapper = mountWithPlugins(MOrderBy, {
      props: { options, orderBy: 'name_asc' },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.findComponent(selectStub).props('modelValue')).toBe('name_asc');
  });

  it('emits update:orderBy when Select emits update:modelValue', async () => {
    const wrapper = mountWithPlugins(MOrderBy, {
      props: { options, orderBy: '' },
      global: { stubs: primevueStubs },
    });
    await wrapper.findComponent(selectStub).vm.$emit('update:modelValue', 'name_desc');
    expect(wrapper.emitted('update:orderBy')).toEqual([['name_desc']]);
  });
});
