import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

const { getTipHistoryService, restoreTipVersionService } = vi.hoisted(() => ({
  getTipHistoryService: vi.fn(),
  restoreTipVersionService: vi.fn(),
}));

vi.mock('../../../services', () => ({
  GetTipHistoryService: getTipHistoryService,
  RestoreTipVersionService: restoreTipVersionService,
}));

import MTipHistoryDialog from './MTipHistoryDialog.vue';

const dialogStub = {
  props: ['isFooterless', 'title', 'visible'],
  emits: ['onClose', 'update:visible'],
  template: '<div v-if="visible" class="dialog-stub"><slot /></div>',
};
const tagStub = {
  props: ['severity', 'value'],
  template: '<span class="tag-stub" :data-tone="severity">{{ value }}</span>',
};

function buildTip(overrides: Record<string, unknown> = {}) {
  return {
    anchor_key: 'users.list',
    archived_at: null,
    content: '<p>Atual</p>',
    created_at: '2026-09-01T10:00:00Z',
    created_by: null,
    id: 4,
    updated_at: '2026-09-03T10:00:00Z',
    updated_by: null,
    ...overrides,
  };
}

function buildEntry(overrides: Record<string, unknown> = {}) {
  return {
    change_reason: null,
    changed_by: { id: 1, name: 'Ana' },
    content: '<p>Atual</p>',
    created_at: '2026-09-03T10:00:00Z',
    event: 'updated',
    id: 20,
    restored_from_id: null,
    ...overrides,
  };
}

function mountDialog(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MTipHistoryDialog, {
    props: { canManage: true, tip: buildTip(), visible: true, ...props },
    global: { stubs: { ConfirmPopup: true, MMainDialog: dialogStub, Skeleton: true, Tag: tagStub } },
  });
}

describe('MTipHistoryDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getTipHistoryService.mockResolvedValue({
      data: [
        buildEntry(),
        buildEntry({ content: '<p>Older</p>', created_at: '2026-09-01T10:00:00Z', event: 'created', id: 10 }),
      ],
    });
  });

  it('loads the history when it opens', async () => {
    mountDialog();
    await flushPromises();

    expect(getTipHistoryService).toHaveBeenCalledWith(4);
  });

  it('shows every version with its author', async () => {
    const wrapper = mountDialog();
    await flushPromises();

    const entries = wrapper.findAll('[data-testid="tip-history-entry"]');

    expect(entries).toHaveLength(2);
    expect(entries[0]!.text()).toContain('Ana');
  });

  it('renders each version body as HTML so the reader sees what it looked like', async () => {
    const wrapper = mountDialog();
    await flushPromises();

    const entries = wrapper.findAll('[data-testid="tip-history-entry"]');

    expect(entries[1]!.find('p').text()).toBe('Older');
  });

  // A link in an old version must not take the reader off the dialog's screen either (tip-002).
  it('opens a link in a version body in a new tab', async () => {
    getTipHistoryService.mockResolvedValue({
      data: [buildEntry({ content: '<p><a href="https://example.test">guide</a></p>' })],
    });
    const wrapper = mountDialog();
    await flushPromises();

    const LINK = wrapper.get('[data-testid="tip-history-entry"] a');

    expect(LINK.attributes('target')).toBe('_blank');
    expect(LINK.attributes('rel')).toBe('noopener noreferrer');
  });

  it('offers no restore on the version that is already live', async () => {
    const wrapper = mountDialog();
    await flushPromises();

    const entries = wrapper.findAll('[data-testid="tip-history-entry"]');

    expect(entries[0]!.find('[data-testid="tip-history-restore"]').exists()).toBe(false);
    expect(entries[1]!.find('[data-testid="tip-history-restore"]').exists()).toBe(true);
  });

  it('does not call the newest row current when it only archived the tip', async () => {
    getTipHistoryService.mockResolvedValue({
      data: [
        buildEntry({ event: 'archived', id: 30 }),
        buildEntry(),
        buildEntry({ content: '<p>Older</p>', event: 'created', id: 10 }),
      ],
    });

    const wrapper = mountDialog();
    await flushPromises();

    const entries = wrapper.findAll('[data-testid="tip-history-entry"]');

    // Archiving changes no text; calling it "current" would claim the tip was last rewritten then.
    expect(entries[0]!.find('[data-testid="tip-history-restore"]').exists()).toBe(true);
    expect(entries[1]!.find('[data-testid="tip-history-restore"]').exists()).toBe(false);
  });

  it('hides restore entirely without tips.manage', async () => {
    const wrapper = mountDialog({ canManage: false });
    await flushPromises();

    expect(wrapper.find('[data-testid="tip-history-restore"]').exists()).toBe(false);
  });

  it('names the version a restore came from instead of just saying "restored"', async () => {
    getTipHistoryService.mockResolvedValue({
      data: [
        buildEntry({ content: '<p>Older</p>', event: 'restored', id: 30, restored_from_id: 10 }),
        buildEntry({ content: '<p>Older</p>', created_at: '2026-09-01T10:00:00Z', event: 'created', id: 10 }),
      ],
    });

    const wrapper = mountDialog({ tip: buildTip({ content: '<p>Older</p>' }) });
    await flushPromises();

    /*
     * The date is built with the same Intl call the component makes, on purpose. What this test
     * asserts is that the line NAMES the version a restore came from — pinning a literal
     * '01/09/2026' would instead assert a locale and a timezone, and go red on a machine that
     * has neither.
     */
    const MOMENT = new Intl.DateTimeFormat('en', { dateStyle: 'short', timeStyle: 'short' }).format(
      new Date('2026-09-01T10:00:00Z')
    );

    expect(wrapper.text()).toContain(`Restored from the version of ${MOMENT}`);
  });

  it('reloads the history after restoring so the new version appears', async () => {
    restoreTipVersionService.mockResolvedValue({ data: buildTip({ content: '<p>Older</p>' }) });

    const wrapper = mountDialog();
    await flushPromises();

    const restoreButton = wrapper
      .findAll('[data-testid="tip-history-entry"]')[1]!
      .get('[data-testid="tip-history-restore"]');
    await restoreButton.trigger('click');

    // The confirm popup is stubbed, so drive the accepted path directly.
    await (wrapper.vm as unknown as { onRestoreConfirm: (entry: { id: number }) => void }).onRestoreConfirm({ id: 10 });
    await flushPromises();

    expect(restoreTipVersionService).toHaveBeenCalledWith(4, 10);
    expect(getTipHistoryService).toHaveBeenCalledTimes(2);
    expect(wrapper.emitted('onRestored')).toBeTruthy();
  });

  it('clears the list when it closes so a stale history never flashes', async () => {
    const wrapper = mountDialog();
    await flushPromises();

    await wrapper.setProps({ visible: false });
    await wrapper.setProps({ visible: true });

    expect(getTipHistoryService).toHaveBeenCalledTimes(2);
  });
});
