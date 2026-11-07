import { nextTick } from 'vue';
import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import { mockUser, mockRole } from '@/test/msw/handlers';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import RolesAndPermissionsView from './RolesAndPermissionsView.view.vue';

const { toastAdd } = vi.hoisted(() => ({ toastAdd: vi.fn() }));

// The toast is asserted where it is ASKED FOR. No <Toast> is mounted in this harness, so nothing
// ever renders one — which is how the old assertion came to pass on a stub's attribute instead.
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

const stubs = {
  MRolesAndPermissionsSkeleton: true,
  MPermissionToolbar: {
    props: ['canUpdate', 'loading', 'tab'],
    emits: ['onSavePermissionsRequest', 'onEditProfileRequest', 'onDeleteProfileRequest'],
    template:
      '<div class="permission-toolbar-stub" :data-can-update="String(canUpdate)"><button class="save-btn" @click="$emit(\'onSavePermissionsRequest\')">Save</button></div>',
  },
  MPermissionPanel: {
    props: ['permissionGroup', 'loading', 'collapsed', 'allPanelsExpanded'],
    emits: ['update:collapsed'],
    // `data-collapsed` and the button are the panel's real contract in miniature: the view owns
    // the state, the panel reports it and asks for it to change.
    template:
      '<div class="permission-panel-stub" :data-collapsed="String(collapsed)" :data-loading="String(loading)">{{ permissionGroup?.nameKey }}<button class="expand-btn" @click="$emit(\'update:collapsed\', false)">Expand</button></div>',
  },
  MConfirmPopup: true,
  MMainDialog: {
    props: ['visible', 'isFooterless', 'title'],
    emits: ['update:visible'],
    template: '<div v-if="visible" class="dialog-stub"><slot /></div>',
  },
  MAddEditProfileForm: {
    props: ['permissionGroups', 'profile'],
    emits: ['onClose', 'onSuccess'],
    template: '<div class="profile-form-stub" />',
  },
  Button: {
    props: ['label', 'icon'],
    template: '<button v-bind="$attrs">{{ label }}</button>',
  },
  Tabs: { props: ['value'], emits: ['update:value'], template: '<div class="tabs-stub"><slot /></div>' },
  TabList: { template: '<div class="tab-list-stub"><slot /></div>' },
  Tab: { props: ['value'], template: '<button class="tab-stub" v-bind="$attrs"><slot /></button>' },
  TabPanels: { template: '<div class="tab-panels-stub"><slot /></div>' },
  TabPanel: { props: ['value'], template: '<div class="tab-panel-stub"><slot /></div>' },
};

const ROLE_EDITOR_PERMISSIONS = [...(mockUser.permissions ?? []), 'permissions.read', 'permissions.update'];

function setupStore(permissions: string[] = ROLE_EDITOR_PERMISSIONS) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(mockUser as never, permissions);
  return pinia;
}

describe('RolesAndPermissionsView — integration (MSW)', () => {
  it('loads roles from the API and renders a tab per role', async () => {
    const secondRole = { ...mockRole, id: 2, name: 'Support', slug: 'support' };
    server.use(http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole, secondRole] })));

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => {
      expect(screen.getByText('Admin')).toBeInTheDocument();
      expect(screen.getByText('Support')).toBeInTheDocument();
    });
  });

  it('shows the permission groups for the first loaded role', async () => {
    server.use(http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })));

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => {
      expect(screen.getByText('permissions.users')).toBeInTheDocument();
    });
  });

  it('calls PATCH on save and view remains intact after success', async () => {
    let patchCalled = false;
    server.use(
      http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })),
      http.patch('http://localhost/auth/roles/:id/permissions', () => {
        patchCalled = true;
        return HttpResponse.json({ data: mockRole });
      }),
      http.get('http://localhost/auth/me', () => HttpResponse.json({ data: mockUser }))
    );

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => screen.getByText('Save'));
    screen.getByText('Save').click();

    // Wait for async save + fetchMe to complete, then confirm PATCH was called
    // and the view did not crash (role data still visible)
    await waitFor(() => expect(patchCalled).toBe(true), { timeout: 3000 });
    expect(screen.getByText('Admin')).toBeInTheDocument();
  });

  /*
   * Saving is not loading. `loading` drives the skeleton, so a save that raised it replaced the
   * whole screen for a request that changes nothing on it — and the page that came back had every
   * group closed again, including the one the moderator had opened to make the edit.
   */
  it('keeps the page up while the save is in flight', async () => {
    let releasePatch = () => {};
    const HELD = new Promise<void>((resolve) => (releasePatch = resolve));
    let patchStarted = false;

    server.use(
      http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })),
      http.patch('http://localhost/auth/roles/:id/permissions', async () => {
        patchStarted = true;
        await HELD;

        return HttpResponse.json({ data: mockRole });
      }),
      http.get('http://localhost/auth/me', () => HttpResponse.json({ data: mockUser }))
    );

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => screen.getByText('Save'));
    screen.getByText('Save').click();

    await waitFor(() => expect(patchStarted).toBe(true));

    expect(document.querySelector('mrolesandpermissionsskeleton-stub')).toBeNull();
    expect(screen.getByText('permissions.users')).toBeInTheDocument();

    releasePatch();
  });

  it('shows a refused save as the rule it broke, not as a failure', async () => {
    /*
     * The API answers 422 with an i18n key when the save asks for something the rule forbids —
     * granting a key the moderator does not hold, say. That is information: a warning in the
     * reader's language. The red "something went wrong" on top would contradict it.
     */
    server.use(
      http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })),
      http.patch('http://localhost/auth/roles/:id/permissions', () =>
        HttpResponse.json(
          { message: 'refused', errors: { permissions: ['rolesAndPermissions.cannotGrantUnheldPermission'] } },
          { status: 422 }
        )
      )
    );

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => screen.getByText('Save'));
    toastAdd.mockClear();
    screen.getByText('Save').click();

    await waitFor(() => expect(toastAdd).toHaveBeenCalled());

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'warn',
        summary: 'You can only grant permissions you hold yourself. Ask someone who has this one.',
      })
    );
    expect(toastAdd).not.toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
  });

  it('leaves the groups the way the moderator left them', async () => {
    /*
     * The sync point matters more than the assertion. "The role is still on screen" is true
     * before the save even starts, so waiting on that asserted the state the test had just set up
     * and passed with the collapse still in place. `/auth/me` is fetched once, at the END of a
     * successful save and AFTER anything the success path does to the panels.
     */
    let meCalls = 0;

    server.use(
      http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })),
      http.patch('http://localhost/auth/roles/:id/permissions', () => HttpResponse.json({ data: mockRole })),
      http.get('http://localhost/auth/me', () => {
        meCalls += 1;

        return HttpResponse.json({ data: mockUser });
      })
    );

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => screen.getByText('Expand'));
    screen.getByText('Expand').click();

    await waitFor(() =>
      expect(document.querySelector('.permission-panel-stub')?.getAttribute('data-collapsed')).toBe('false')
    );

    screen.getByText('Save').click();

    await waitFor(() => expect(meCalls).toBeGreaterThan(0));
    await nextTick();
    await nextTick();

    expect(document.querySelector('.permission-panel-stub')?.getAttribute('data-collapsed')).toBe('false');
  });

  it('shows an error toast when the roles fetch fails', async () => {
    server.use(http.get('http://localhost/auth/roles', () => HttpResponse.error()));

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    /*
     * It used to accept `document.body.innerHTML.includes('error')`, and that passed on a stub: the
     * ConfirmDialog stub carried `group="delete-error"` into the markup. With the dialog gone the
     * test failed — which is the first time it said anything. It now asserts the error toast itself.
     */
    await waitFor(() => expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' })), {
      timeout: 3000,
    });
  });

  /*
   * The route opens on `permissions.read`; creating, editing, deleting and saving a role all ask the
   * API for `permissions.update`. A reader saw every write control, and each one answered 403.
   */
  it('shows a reader the roles without any control that writes them', async () => {
    server.use(http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })));

    const pinia = setupStore(['permissions.read']);
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => expect(screen.getByText('permissions.users')).toBeInTheDocument());

    expect(document.querySelector('[data-testid="add-profile"]')).toBeNull();
    expect(document.querySelector('.permission-toolbar-stub')?.getAttribute('data-can-update')).toBe('false');
    expect(document.querySelector('.permission-panel-stub')?.getAttribute('data-loading')).toBe('true');
  });

  it('offers the write controls to an account holding permissions.update', async () => {
    server.use(http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: [mockRole] })));

    const pinia = setupStore();
    renderWithPlugins(RolesAndPermissionsView, { pinia, global: { stubs } });

    await waitFor(() => expect(screen.getByText('permissions.users')).toBeInTheDocument());

    expect(document.querySelector('[data-testid="add-profile"]')).not.toBeNull();
    expect(document.querySelector('.permission-toolbar-stub')?.getAttribute('data-can-update')).toBe('true');
    expect(document.querySelector('.permission-panel-stub')?.getAttribute('data-loading')).toBe('false');
  });
});
