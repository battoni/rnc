import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import { paginated } from '@/test/msw/handlers';

// vi.hoisted ensures mock vars are initialised before the hoisted vi.mock factories run.
const { getUsersService, archiveUserService, deleteUserService } = vi.hoisted(() => ({
  getUsersService: vi.fn(),
  archiveUserService: vi.fn(),
  deleteUserService: vi.fn(),
}));

vi.mock('../../services', () => ({
  GetUsersService: getUsersService,
  ArchiveUserService: archiveUserService,
  DeleteUserService: deleteUserService,
}));

const storePermissions: string[] = [];
const storeUser: { id: number } | null = { id: 1 };

vi.mock('../../store', () => ({
  useUserStore: () => ({
    hasPermission: (key: string) => storePermissions.includes(key),
    user: storeUser,
  }),
}));

import UsersView from './UsersView.view.vue';

const layoutStub = { template: '<div class="layout-stub"><slot name="pageHeader" /><slot /></div>' };
const pageHeaderStub = {
  template: '<div class="page-header-stub"><slot name="primaryAction" /><slot name="actions" /></div>',
};
const morderByStub = { props: ['options', 'orderBy'], emits: ['update:orderBy'], template: '<div />' };
const dialogStub = {
  props: ['visible', 'isFooterless', 'title'],
  emits: ['update:visible'],
  template: '<div v-if="visible" class="dialog-stub"><slot /></div>',
};
const userFormStub = {
  name: 'MAddEditUserForm',
  props: ['user'],
  emits: ['onClose', 'onSuccess'],
  template: '<div class="user-form-stub" />',
};
const listStub = {
  name: 'OListDivided',
  props: ['items', 'canEdit', 'badge', 'status', 'statusIconMap', 'subtitle', 'title', 'showDetails', 'itemKey'],
  emits: ['onEdit'],
  // Expose whether the parent mounted `#actions`: OListDivided treats a present slot as "has
  // actions", and a read-only viewer must not mount one (empty ⋮).
  template: `<div class="list-stub" :data-has-actions-slot="Boolean($slots.actions)">
    <div v-for="item in items" class="row-stub" :data-id="item.id">
      <span>{{ item.displayName }}</span>
      <slot name="actions" :item="item" variant="inline" :close="() => {}" />
    </div>
  </div>`,
};
const paginatorStub = {
  name: 'OPaginator',
  props: ['page', 'perPage', 'total'],
  emits: ['onPage'],
  template: '<div />',
};
const buttonStub = {
  props: ['label', 'icon', 'class', 'ariaLabel'],
  template: '<button class="button-stub" :aria-label="ariaLabel" v-bind="$attrs">{{ label }}</button>',
};

const multiSelectStub = {
  name: 'MultiSelect',
  props: ['modelValue', 'options'],
  emits: ['update:modelValue'],
  methods: { hide() {} },
  template: '<div class="multiselect-stub"><slot name="footer" /></div>',
};

const globalStubs = {
  TheLayout: layoutStub,
  ThePageHeader: pageHeaderStub,
  MOrderBy: morderByStub,
  MMainDialog: dialogStub,
  MAddEditUserForm: userFormStub,
  OListDivided: listStub,
  OPaginator: paginatorStub,
  Button: buttonStub,
  ConfirmPopup: true,
  InputGroup: { template: '<div><slot /></div>' },
  InputGroupAddon: true,
  MultiSelect: multiSelectStub,
  Skeleton: true,
};

const users = [
  { id: 1, name: 'Alice', last_name: 'Smith', username: 'alice', role: 'Admin', status: 'active' },
  { id: 2, name: 'Bob', last_name: 'Jones', username: 'bob', role: 'User', status: 'inactive' },
];

function mount() {
  return mountWithPlugins(UsersView, { global: { stubs: globalStubs } });
}

describe('UsersView', () => {
  beforeEach(() => {
    storePermissions.length = 0;
    getUsersService.mockReset();
    archiveUserService.mockReset();
    deleteUserService.mockReset();
  });

  it('does not call GetUsersService when user lacks users.read permission', async () => {
    mount();
    await flushPromises();
    expect(getUsersService).not.toHaveBeenCalled();
  });

  it('loads the first page and, separately, the names for the search', async () => {
    storePermissions.push('users.read');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    mount();
    await flushPromises();

    expect(getUsersService).toHaveBeenCalledWith(expect.objectContaining({ page: 1, per_page: 15 }));
    expect(getUsersService).toHaveBeenCalledWith(expect.objectContaining({ per_page: 100 }));
  });

  it('renders a row per user of the page', async () => {
    storePermissions.push('users.read');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.findAll('.row-stub')).toHaveLength(users.length);
    expect(wrapper.text()).toContain('Alice Smith');
    expect(wrapper.text()).toContain('Bob Jones');
  });

  it('does not mount #actions when the viewer can only read', async () => {
    storePermissions.push('users.read');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.find('.list-stub').attributes('data-has-actions-slot')).toBe('false');
  });

  it('mounts #actions when the viewer can archive', async () => {
    storePermissions.push('users.read', 'users.archive');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.find('.list-stub').attributes('data-has-actions-slot')).toBe('true');
  });

  it('never offers the signed-in administrator a way to deactivate themselves', async () => {
    storePermissions.push('users.read', 'users.archive');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();

    const ownRow = wrapper.find('.row-stub[data-id="1"]');
    const otherRow = wrapper.find('.row-stub[data-id="2"]');

    expect(ownRow.find('[aria-label="Archive"]').exists()).toBe(false);
    expect(otherRow.find('[aria-label="Activate"]').exists()).toBe(true);
  });

  it('offers delete only for an inactive account', async () => {
    storePermissions.push('users.read', 'users.delete');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.find('.row-stub[data-id="1"] [aria-label="Delete"]').exists()).toBe(false);
    expect(wrapper.find('.row-stub[data-id="2"] [aria-label="Delete"]').exists()).toBe(true);
  });

  it('opens the dialog when the Add button is clicked', async () => {
    storePermissions.push('users.read', 'users.create');
    getUsersService.mockResolvedValue({ data: paginated([]) });
    const wrapper = mount();
    await flushPromises();
    await wrapper.find('[data-testid="add-user"]').trigger('click');
    expect(wrapper.find('.dialog-stub').exists()).toBe(true);
  });

  it('prepends a newly created user through the paginator, without refetching', async () => {
    storePermissions.push('users.read', 'users.create');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();
    const callsAfterMount = getUsersService.mock.calls.length;

    await wrapper.find('[data-testid="add-user"]').trigger('click');

    const newUser = { id: 99, name: 'Carol', last_name: 'White', username: 'carol', role: 'User', status: 'active' };
    await wrapper.findComponent(userFormStub).vm.$emit('onSuccess', newUser);
    await flushPromises();

    const rows = wrapper.findAll('.row-stub');
    expect(rows).toHaveLength(users.length + 1);
    expect(rows[0]?.text()).toContain('Carol');
    expect(wrapper.findComponent(paginatorStub).props('total')).toBe(users.length + 1);
    expect(getUsersService).toHaveBeenCalledTimes(callsAfterMount);
  });

  it('replaces an updated user in the list without refetching', async () => {
    storePermissions.push('users.read', 'users.create');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();
    const callsAfterMount = getUsersService.mock.calls.length;

    await wrapper.find('[data-testid="add-user"]').trigger('click');

    const updatedUser = { ...users[0], name: 'Alicia' };
    await wrapper.findComponent(userFormStub).vm.$emit('onSuccess', updatedUser);
    await flushPromises();

    expect(wrapper.findAll('.row-stub')).toHaveLength(users.length);
    expect(wrapper.text()).toContain('Alicia Smith');
    expect(getUsersService).toHaveBeenCalledTimes(callsAfterMount);
  });

  it('asks page 1 again instead of prepending while a search filters the list', async () => {
    storePermissions.push('users.read', 'users.create');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();

    await wrapper.findComponent(multiSelectStub).vm.$emit('update:modelValue', [1]);
    const searchButton = wrapper.findAll('.multiselect-stub button').find((button) => button.text() === 'Search');
    await searchButton!.trigger('click');
    await flushPromises();

    const filteredUsers = [users[0]!];
    getUsersService.mockResolvedValue({ data: paginated(filteredUsers) });
    const callsBeforeCreate = getUsersService.mock.calls.length;

    await wrapper.find('[data-testid="add-user"]').trigger('click');

    const newUser = { id: 99, name: 'Carol', last_name: 'White', username: 'carol', role: 'User', status: 'active' };
    await wrapper.findComponent(userFormStub).vm.$emit('onSuccess', newUser);
    await flushPromises();

    // The new account does not match the search: it must not appear above rows that do.
    expect(wrapper.text()).not.toContain('Carol White');
    expect(wrapper.findAll('.row-stub')).toHaveLength(filteredUsers.length);
    expect(getUsersService).toHaveBeenCalledTimes(callsBeforeCreate + 1);
    expect(getUsersService).toHaveBeenLastCalledWith(expect.objectContaining({ ids: [1], page: 1 }));
  });

  it('does not treat names picked but not yet searched as an active filter', async () => {
    storePermissions.push('users.read', 'users.create');
    getUsersService.mockResolvedValue({ data: paginated(users) });
    const wrapper = mount();
    await flushPromises();
    const callsAfterMount = getUsersService.mock.calls.length;

    await wrapper.findComponent(multiSelectStub).vm.$emit('update:modelValue', [1]);
    await wrapper.find('[data-testid="add-user"]').trigger('click');

    const newUser = { id: 99, name: 'Carol', last_name: 'White', username: 'carol', role: 'User', status: 'active' };
    await wrapper.findComponent(userFormStub).vm.$emit('onSuccess', newUser);
    await flushPromises();

    expect(wrapper.findAll('.row-stub')[0]?.text()).toContain('Carol');
    expect(getUsersService).toHaveBeenCalledTimes(callsAfterMount);
  });
});
