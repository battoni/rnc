import { ref } from 'vue';
import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';

const { archiveTipService, getTipsService, toastAdd, unarchiveTipService } = vi.hoisted(() => ({
  archiveTipService: vi.fn(),
  getTipsService: vi.fn(),
  toastAdd: vi.fn(),
  unarchiveTipService: vi.fn(),
}));

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

vi.mock('../../services', () => ({
  ArchiveTipService: archiveTipService,
  GetTipsService: getTipsService,
  UnarchiveTipService: unarchiveTipService,
}));

let storePermissions: string[] = [];

vi.mock('@UserModule', () => ({
  useUserStore: () => ({
    hasPermission: (key: string) => storePermissions.includes(key),
  }),
}));

const applyTip = vi.fn();

/*
 * The view resolves an anchor's route to build the "see in app" link. The stub reproduces enough
 * of vue-router's resolve — the query folded into `fullPath` — for the spec to assert the row
 * links to the screen the tip actually renders on.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<typeof VueRouter>()),
  useRouter: () => ({
    resolve: ({ name, query = {} }: { name: string; query?: Record<string, string> }) => {
      const path = `/${name}`;
      const search = new URLSearchParams(query).toString();

      return { fullPath: search ? `${path}?${search}` : path, path };
    },
  }),
}));

vi.mock('@Stores', () => ({
  useTipStore: () => ({ applyTip }),
}));

/*
 * The "see in app" control changes shape with the viewport, so the viewport is a knob the spec
 * turns rather than an ambient truth of jsdom.
 */
const isMobile = ref(false);

vi.mock('@Composables', () => ({
  useViewport: () => ({ isMobile }),
}));

import TipsView from './TipsView.view.vue';

const layoutStub = { template: '<div class="layout-stub"><slot name="pageHeader" /><slot /></div>' };
const pageHeaderStub = {
  template: '<div class="page-header-stub"><slot name="primaryAction" /><slot name="actions" /></div>',
};
const pageHeaderActionStub = {
  props: ['disabled', 'icon', 'label'],
  template: '<button class="page-header-action-stub" :disabled="disabled">{{ label }}</button>',
};
const dialogStub = {
  props: ['visible', 'isFooterless', 'submitButtonProps', 'title'],
  emits: ['update:visible', 'onClose', 'onSubmit'],
  template: '<div v-if="visible" class="dialog-stub"><slot /></div>',
};
const historyDialogStub = {
  name: 'MTipHistoryDialog',
  props: ['canManage', 'tip', 'visible'],
  emits: ['update:visible', 'onRestored'],
  template: '<div v-if="visible" class="history-dialog-stub" />',
};
const tipFormStub = {
  name: 'MAddEditTipForm',
  props: ['takenAnchorKeys', 'tip'],
  emits: ['onClose', 'onSuccess'],
  template: '<div class="tip-form-stub" />',
};
/* PrimeVue components are auto-imported in the app but not registered in tests; these stubs give
   the fields real DOM inputs so the spec can type into them. */
// PrimeVue Button renders its text from a `label` prop; unstubbed it would leave the row empty.
const buttonStub = {
  props: ['label'],
  template: '<button v-bind="$attrs">{{ label }}</button>',
};
const inputTextStub = {
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template: `<input v-bind="$attrs" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
};
const toggleSwitchStub = {
  props: ['inputId', 'modelValue'],
  emits: ['update:modelValue'],
  template: `<input type="checkbox" :id="inputId" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />`,
};
const listStub = {
  name: 'OListDivided',
  props: ['canEdit', 'items', 'status', 'subtitle', 'title'],
  emits: ['onEdit'],
  // Renders both slot variants so the view's menu vs inline shapes can be asserted without
  // mounting the real organism (container queries do not fold in jsdom).
  template:
    '<div class="list-stub"><div v-for="item in items" class="list-row" :key="item.id"><span class="row-title">{{ item.anchorLabel }}</span><span class="row-section">{{ item.sectionLabel }}</span><div data-testid="actions-inline"><slot name="actions" :item="item" variant="inline" /></div><div data-testid="actions-menu"><slot name="actions" :item="item" variant="menu" /></div><slot name="details" :item="item" /></div></div>',
};

function buildTip(overrides: Record<string, unknown> = {}) {
  return {
    anchor_key: 'users.list',
    archived_at: null,
    content: '<p>Every person who can sign in.</p>',
    created_at: '2026-09-01T10:00:00Z',
    created_by: null,
    id: 1,
    updated_at: '2026-09-01T10:00:00Z',
    updated_by: { id: 3, name: 'Ana' },
    ...overrides,
  };
}

function mountView() {
  return mountWithPlugins(TipsView, {
    global: {
      stubs: {
        APageHeaderAction: pageHeaderActionStub,
        Button: buttonStub,
        InputText: inputTextStub,
        ToggleSwitch: toggleSwitchStub,
        MAddEditTipForm: tipFormStub,
        MMainDialog: dialogStub,
        MTipHistoryDialog: historyDialogStub,
        OListDivided: listStub,
        // Renders `to` so the spec can assert the destination the row actually links to.
        RouterLink: { props: ['to'], template: '<a :to="to"><slot /></a>' },
        TheLayout: layoutStub,
        ThePageHeader: pageHeaderStub,
      },
    },
  });
}

describe('TipsView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    isMobile.value = false;
    storePermissions = ['tips.read', 'tips.manage'];
    getTipsService.mockResolvedValue({ data: [buildTip()] });
  });

  it('does not fetch without tips.read', async () => {
    storePermissions = [];

    const wrapper = mountView();
    await flushPromises();

    expect(getTipsService).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("You don't have permission to see tips.");
  });

  it('shows where each tip is used, not just its key', async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.get('.row-title').text()).toBe('Users list');
    expect(wrapper.get('.row-section').text()).toBe('Administration');
  });

  it('flags a tip whose anchor no longer exists in the code', async () => {
    getTipsService.mockResolvedValue({ data: [buildTip({ anchor_key: 'removed.screen' })] });

    const wrapper = mountView();
    await flushPromises();

    // Falling back to the raw key is what makes a renamed screen visible instead of a blank row.
    expect(wrapper.get('.row-title').text()).toBe('removed.screen');
    expect(wrapper.text()).toContain('the tip is not showing anywhere');
  });

  it('filters by the tip text, not only by its label', async () => {
    getTipsService.mockResolvedValue({
      data: [buildTip(), buildTip({ anchor_key: 'roles.permissions', content: '<p>Etapas de produção.</p>', id: 2 })],
    });

    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('#tips-search').setValue('produção');

    expect(wrapper.findAll('.list-row')).toHaveLength(1);
    expect(wrapper.get('.row-title').text()).toBe('Roles and permissions');
  });

  it('hides the create action without tips.manage', async () => {
    storePermissions = ['tips.read'];

    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.find('.page-header-action-stub').exists()).toBe(false);
  });

  it('disables the create action when every anchor is taken', async () => {
    const { TIP_ANCHORS } = await import('@Constants');
    getTipsService.mockResolvedValue({
      data: TIP_ANCHORS.map((anchor, index) => buildTip({ anchor_key: anchor.key, id: index + 1 })),
    });

    const wrapper = mountView();
    await flushPromises();

    // Offering "new tip" with nowhere to put it is how orphan tips get created.
    expect(wrapper.get('.page-header-action-stub').attributes('disabled')).toBeDefined();
  });

  it('refuses to archive until a reason is typed', async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="tip-archive"]').trigger('click');
    await flushPromises();

    const dialog = wrapper.findComponent(dialogStub);
    expect(dialog.props('submitButtonProps').disabled).toBe(true);

    await wrapper.get('[data-testid="tip-archive-reason"]').setValue('no longer applies');

    expect(wrapper.findComponent(dialogStub).props('submitButtonProps').disabled).toBe(false);
  });

  it('archives with the typed reason and drops the row from the active list', async () => {
    archiveTipService.mockResolvedValue({ data: buildTip({ archived_at: '2026-09-04T12:00:00Z' }) });

    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="tip-archive"]').trigger('click');
    await wrapper.get('[data-testid="tip-archive-reason"]').setValue('no longer applies');

    wrapper.findComponent(dialogStub).vm.$emit('onSubmit');
    await flushPromises();

    expect(archiveTipService).toHaveBeenCalledWith(1, 'no longer applies');
    expect(wrapper.findAll('.list-row')).toHaveLength(0);
    // The rest of the app stops showing it without a reload.
    expect(applyTip).toHaveBeenCalled();
  });

  it('reloads from the API when archived rows are toggled on', async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('#tips-show-archived').setValue(true);
    await flushPromises();

    expect(getTipsService).toHaveBeenLastCalledWith(true);
  });

  it('replaces the row in place after an edit instead of refetching', async () => {
    const wrapper = mountView();
    await flushPromises();

    wrapper.findComponent(listStub).vm.$emit('onEdit', { id: 1 });
    await flushPromises();

    wrapper.findComponent(tipFormStub).vm.$emit('onSuccess', buildTip({ content: '<p>Reescrito.</p>' }));
    await flushPromises();

    expect(getTipsService).toHaveBeenCalledOnce();
    expect(applyTip).toHaveBeenCalled();
  });

  it('links straight to the screen the tip renders on', async () => {
    const wrapper = mountView();
    await flushPromises();

    const link = wrapper.get('[data-testid="actions-inline"] [data-testid="tip-go-to-place"]');

    // The route stem is resolved through the active locale (tests run in en).
    expect(link.attributes('to')).toBe('/users-en');
    // Icon-only was unreadable; where there is room, the row says where it goes.
    expect(link.text()).toContain('See in app');
  });

  it('drops the label on a phone, where it would fight the title for width', async () => {
    isMobile.value = true;

    const wrapper = mountView();
    await flushPromises();

    const link = wrapper.get('[data-testid="actions-inline"] [data-testid="tip-go-to-place"]');

    expect(link.text()).not.toContain('See in app');
    // The reading must not depend on how wide the window is.
    expect(link.get('button').attributes('aria-label')).toBe('See in app');
  });

  it('still links an archived tip to where it used to live', async () => {
    getTipsService.mockResolvedValue({ data: [buildTip({ archived_at: '2026-09-04T12:00:00Z' })] });

    const wrapper = mountView();
    await flushPromises();

    // Knowing where it sat is exactly what you check before reactivating it.
    expect(wrapper.find('[data-testid="actions-inline"] [data-testid="tip-go-to-place"]').exists()).toBe(true);
  });

  /*
   * The ⋮ is the only action strip on a narrow list. Custom #actions that stay icon-only there
   * leave History / Archive / See in app as unmarked icons beside a labelled Edit — unreadable.
   * OListDivided passes variant: 'menu'; this view must put a label on every row.
   */
  it('labels History, Archive and See in app inside the overflow menu', async () => {
    const wrapper = mountView();
    await flushPromises();

    const menu = wrapper.get('[data-testid="actions-menu"]');

    expect(menu.get('[data-testid="tip-history"]').text()).toContain('Tip history');
    expect(menu.get('[data-testid="tip-archive"]').text()).toContain('Archive');
    expect(menu.get('[data-testid="tip-go-to-place"]').text()).toContain('See in app');
  });

  // The row detail renders the tip; a link in it must not navigate /tips away (tip-002).
  it('opens a link in a tip body in a new tab', async () => {
    getTipsService.mockResolvedValue({
      data: [buildTip({ content: '<p><a href="https://example.test">guide</a></p>' })],
    });

    const wrapper = mountView();
    await flushPromises();

    const LINK = wrapper.get('.list-row p a');

    expect(LINK.attributes('target')).toBe('_blank');
    expect(LINK.attributes('rel')).toBe('noopener noreferrer');
  });

  // The tones OListDivided paints; any other value renders the bare default Tag, 1.83:1 (VIS-04).
  it('gives an archived row a tone the list knows how to paint', async () => {
    getTipsService.mockResolvedValue({ data: [buildTip({ archived_at: '2026-09-04T12:00:00Z' })] });

    const wrapper = mountView();
    await flushPromises();

    const [row] = wrapper.findComponent(listStub).props('items') as Array<{ statusTone: string }>;

    expect(['contrast', 'danger', 'error', 'info', 'primary', 'success', 'warn', 'warning']).toContain(row!.statusTone);
  });

  describe('when the API refuses a row action (422)', () => {
    const ANCHOR_TAKEN = {
      response: { status: 422, data: { errors: { anchor_key: ['tips.errors.anchorTaken'] } } },
    };

    // A 422 names a rule: a warning that says which, not the red "contact the admin" (celer-07).
    it('warns with the translated rule when an unarchive finds the place taken', async () => {
      getTipsService.mockResolvedValue({ data: [buildTip({ archived_at: '2026-09-04T12:00:00Z' })] });
      unarchiveTipService.mockRejectedValue(ANCHOR_TAKEN);

      const wrapper = mountView();
      await flushPromises();

      await wrapper.get('[data-testid="actions-inline"] [data-testid="tip-unarchive"]').trigger('click');
      await flushPromises();

      expect(toastAdd).toHaveBeenCalledWith(
        expect.objectContaining({ severity: 'warn', summary: 'There is already an active tip in this place.' })
      );
    });

    it('warns with the translated rule when an archive is refused', async () => {
      archiveTipService.mockRejectedValue({
        response: { status: 422, data: { errors: { change_reason: ['tips.errors.reasonRequired'] } } },
      });

      const wrapper = mountView();
      await flushPromises();

      await wrapper.get('[data-testid="actions-inline"] [data-testid="tip-archive"]').trigger('click');
      wrapper.findComponent(dialogStub).vm.$emit('onSubmit');
      await flushPromises();

      // No reason typed: the view refuses on its own first, so type one.
      await wrapper.get('[data-testid="tip-archive-reason"]').setValue('Obsolete');
      wrapper.findComponent(dialogStub).vm.$emit('onSubmit');
      await flushPromises();

      expect(toastAdd).toHaveBeenCalledWith(
        expect.objectContaining({ severity: 'warn', summary: 'A change reason is required.' })
      );
    });

    it('keeps the red toast for a failure that is not a refusal', async () => {
      getTipsService.mockResolvedValue({ data: [buildTip({ archived_at: '2026-09-04T12:00:00Z' })] });
      unarchiveTipService.mockRejectedValue({ response: { status: 500 } });

      const wrapper = mountView();
      await flushPromises();

      await wrapper.get('[data-testid="actions-inline"] [data-testid="tip-unarchive"]').trigger('click');
      await flushPromises();

      expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
    });
  });
});
