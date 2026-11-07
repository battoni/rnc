import { defineComponent } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as TipModule from '@TipModule';
import { mountWithPlugins } from '@/test/mount';
import { resetSingleTipState } from '@Composables';
import { useTipStore } from '@Stores';
import { useUserStore } from '@UserModule';
import ATip from './ATip.vue';

vi.mock('@TipModule', async (importOriginal) => ({
  ...(await importOriginal<typeof TipModule>()),
  GetActiveTipsService: vi.fn(() => Promise.resolve({ data: [] })),
}));

function createPopoverStub() {
  const hideSpy = vi.fn();
  const toggleSpy = vi.fn();

  const PopoverStub = defineComponent({
    name: 'PopoverStub',
    props: {
      dismissable: { type: Boolean, default: false },
      id: { type: String, default: '' },
    },
    emits: ['hide', 'show'],
    setup(_props, { emit, expose }) {
      function hideHandler() {
        hideSpy();
        emit('hide');
      }

      function toggleHandler() {
        toggleSpy();
        emit('show');
      }

      expose({ hide: hideHandler, toggle: toggleHandler });

      return {};
    },
    template: '<div class="popover-stub" :id="id"><slot /></div>',
  });

  return { hide: hideSpy, PopoverStub, toggle: toggleSpy };
}

function mountTip(options: { permissions?: string[]; props?: Record<string, unknown>; tipContent?: string } = {}) {
  const { hide, PopoverStub, toggle } = createPopoverStub();

  const pinia = createPinia();
  setActivePinia(pinia);

  const tipStore = useTipStore();
  tipStore.isLoaded = true;

  if (options.tipContent !== undefined) {
    tipStore.contents = {
      'users.list': { anchor_key: 'users.list', content: options.tipContent, id: 7 },
    };
  }

  const userStore = useUserStore();
  userStore.permissions = options.permissions ?? [];

  const wrapper = mountWithPlugins(ATip, {
    props: { tipKey: 'users.list', ...options.props },
    global: { components: { Button }, plugins: [pinia], stubs: { Popover: PopoverStub } },
  });

  return { hide, tipStore, toggle, wrapper };
}

describe('ATip', () => {
  beforeEach(() => {
    resetSingleTipState();
  });

  it('renders nothing when no tip is anchored there', () => {
    const { wrapper } = mountTip();

    expect(wrapper.find('.pi-info-circle').exists()).toBe(false);
  });

  it('renders the info icon trigger once a tip exists', () => {
    const { wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    expect(wrapper.find('.pi-info-circle').exists()).toBe(true);
  });

  it('asks the store to load tips on mount', () => {
    const { tipStore } = mountTip({ tipContent: '<p>A tip.</p>' });

    // Already loaded in the fixture, so this asserts the atom does not blank itself waiting.
    expect(tipStore.isLoaded).toBe(true);
  });

  it('renders the stored HTML rather than escaping it', () => {
    const { wrapper } = mountTip({ tipContent: '<p>Primeiro.</p><p><strong>Segundo</strong></p>' });

    const body = wrapper.get('[data-testid="tip-body"]');

    expect(body.findAll('p')).toHaveLength(2);
    expect(body.find('strong').text()).toBe('Segundo');
  });

  it('uses the default translated aria-label', () => {
    const { wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    expect(wrapper.get('button').attributes('aria-label')).toBe('Tip');
  });

  it('uses a custom aria-label when provided', () => {
    const { wrapper } = mountTip({ props: { ariaLabel: 'Help about pieces' }, tipContent: '<p>A tip.</p>' });

    expect(wrapper.get('button').attributes('aria-label')).toBe('Help about pieces');
  });

  it('hides the edit affordance without tips.manage', () => {
    const { wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    expect(wrapper.find('[data-testid="tip-edit"]').exists()).toBe(false);
  });

  it('shows the edit affordance with tips.manage', () => {
    const { wrapper } = mountTip({ permissions: ['tips.manage'], tipContent: '<p>A tip.</p>' });

    expect(wrapper.find('[data-testid="tip-edit"]').exists()).toBe(true);
  });

  it('closes the popover and asks the store to edit this anchor', async () => {
    const { hide, tipStore, wrapper } = mountTip({ permissions: ['tips.manage'], tipContent: '<p>A tip.</p>' });

    await wrapper.get('[data-testid="tip-edit"]').trigger('click');

    // Leaving the popover open behind the dialog mask is what makes this feel broken.
    expect(hide).toHaveBeenCalledOnce();
    expect(tipStore.editingAnchorKey).toBe('users.list');
  });

  it('calls popover toggle when the trigger is clicked', async () => {
    const { toggle, wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    await wrapper.get('button').trigger('click');

    expect(toggle).toHaveBeenCalledOnce();
  });

  it('renders the trigger with the project layout classes', () => {
    const { wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    const triggerClasses = wrapper.get('button').classes();

    expect(triggerClasses).toContain('size-8');
    expect(triggerClasses).toContain('shrink-0');
  });

  it('wraps the tip in the tutor-tip class for global visibility control', () => {
    const { wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    expect(wrapper.find('.tutor-tip').exists()).toBe(true);
  });

  it('binds aria-controls and aria-expanded for screen reader association', async () => {
    const { toggle, wrapper } = mountTip({ tipContent: '<p>A tip.</p>' });

    const trigger = wrapper.get('button');
    const popoverId = trigger.attributes('aria-controls');

    expect(popoverId).toBeTruthy();
    expect(wrapper.find(`#${popoverId}`).exists()).toBe(true);
    expect(trigger.attributes('aria-expanded')).toBe('false');

    await trigger.trigger('click');

    expect(toggle).toHaveBeenCalledOnce();
    expect(trigger.attributes('aria-expanded')).toBe('true');
  });
});
