import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MSearchMultiselect from './MSearchMultiselect.vue';

const multiselectStub = {
  name: 'MultiSelect',
  props: ['modelValue', 'options', 'optionLabel', 'optionValue', 'filter', 'display', 'inputId'],
  emits: ['update:modelValue'],
  template: '<div class="multiselect-stub" />',
};

const primevueStubs = {
  InputGroup: { template: '<div class="input-group-stub"><slot /></div>' },
  InputGroupAddon: { template: '<div class="input-group-addon-stub"><slot /></div>' },
  FloatLabel: { template: '<div class="float-label-stub"><slot /></div>' },
  MultiSelect: multiselectStub,
};

const options = [
  { label: 'Alice', value: 1 },
  { label: 'Bob', value: 2 },
];

describe('MSearchMultiselect', () => {
  it('renders without errors', () => {
    const wrapper = mountWithPlugins(MSearchMultiselect, {
      props: { options },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.exists()).toBe(true);
  });

  it('renders the search label (i18n key common.search = "Search")', () => {
    const wrapper = mountWithPlugins(MSearchMultiselect, {
      props: { options },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.text()).toContain('Search');
  });

  it('passes options to the MultiSelect stub', () => {
    const wrapper = mountWithPlugins(MSearchMultiselect, {
      props: { options },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.findComponent(multiselectStub).props('options')).toEqual(options);
  });

  it('passes the current search value to MultiSelect', () => {
    const wrapper = mountWithPlugins(MSearchMultiselect, {
      props: { options, search: 'Alice' },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.findComponent(multiselectStub).props('modelValue')).toBe('Alice');
  });

  it('emits update:search when MultiSelect emits update:modelValue', async () => {
    const wrapper = mountWithPlugins(MSearchMultiselect, {
      props: { options, search: '' },
      global: { stubs: primevueStubs },
    });
    await wrapper.findComponent(multiselectStub).vm.$emit('update:modelValue', 'Bob');
    expect(wrapper.emitted('update:search')).toEqual([['Bob']]);
  });
});
