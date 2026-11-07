import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import { mockUser, paginated } from '@/test/msw/handlers';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '../../store';
import UsersView from './UsersView.view.vue';

vi.mock('primevue/useconfirm', () => ({
  useConfirm: () => ({
    require: ({ accept }: { accept: () => void }) => accept(),
  }),
}));

const stubs = {
  OListDivided: {
    props: ['items', 'canEdit'],
    emits: ['onEdit'],
    template: `<div>
      <div v-for="item in items" class="row-stub" :data-id="item.id">
        <span>{{ item.displayName }}</span>
        <button v-if="canEdit" class="edit-btn" @click="$emit('onEdit', item)">edit</button>
        <slot name="actions" :item="item" variant="inline" :close="() => {}" />
      </div>
    </div>`,
  },
  OPaginator: { props: ['page', 'perPage', 'total'], template: '<div class="paginator-stub" :data-total="total" />' },
  MMainDialog: {
    props: ['visible', 'isFooterless', 'title'],
    emits: ['update:visible'],
    template: '<div v-if="visible" class="dialog-stub"><slot /></div>',
  },
  MAddEditUserForm: {
    name: 'MAddEditUserForm',
    props: ['user'],
    emits: ['onClose', 'onSuccess'],
    template: `<div class="user-form-stub">
      <button class="form-submit" @click="$emit('onSuccess', user ? {...user, name: 'Alicia'} : {id: 99, name: 'New', last_name: 'User', username: 'new', role: 'User', status: 'active'})">submit</button>
    </div>`,
  },
  MOrderBy: { props: ['options', 'orderBy'], emits: ['update:orderBy'], template: '<div />' },
  Button: {
    props: ['label', 'icon', 'class', 'loading', 'ariaLabel'],
    template: '<button :aria-label="ariaLabel" v-bind="$attrs">{{ label }}</button>',
  },
  ConfirmPopup: true,
  InputGroup: { template: '<div><slot /></div>' },
  InputGroupAddon: true,
  MultiSelect: true,
  Skeleton: true,
};

const inactiveUser = { ...mockUser, id: 2, name: 'Bob', last_name: 'Jones', status: 'inactive' };

function setupWithPermissions(permissions: string[]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(mockUser as never, permissions);
  return pinia;
}

describe('UsersView — integration (MSW)', () => {
  it('reads the rows out of the paginated envelope', async () => {
    server.use(
      http.get('http://localhost/auth/users', () =>
        HttpResponse.json({ data: paginated([mockUser, inactiveUser], { total: 40 }) })
      )
    );

    const pinia = setupWithPermissions(['users.read']);
    renderWithPlugins(UsersView, { pinia, global: { stubs } });

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
      expect(screen.getByText('Bob Jones')).toBeInTheDocument();
    });

    expect(document.querySelector('.paginator-stub')?.getAttribute('data-total')).toBe('40');
  });

  it('shows nothing and skips the fetch when user lacks users.read', async () => {
    const pinia = setupWithPermissions([]);
    renderWithPlugins(UsersView, { pinia, global: { stubs } });

    await new Promise((resolve) => setTimeout(resolve, 50));
    expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
  });

  // A failed load is not an empty list: "No users yet" + "Add User" after a 500 told the person
  // their data was gone (celer-07, lifeshifter-350).
  it('says the list did not load, not that it is empty, and loads again on Try again', async () => {
    let attempts = 0;
    server.use(
      http.get('http://localhost/auth/users', ({ request }) => {
        if (!new URL(request.url).searchParams.get('page')) return HttpResponse.json({ data: paginated([]) });

        attempts += 1;

        return attempts === 1 ? HttpResponse.error() : HttpResponse.json({ data: paginated([mockUser]) });
      })
    );

    const pinia = setupWithPermissions(['users.read', 'users.create']);
    renderWithPlugins(UsersView, { pinia, global: { stubs } });

    await waitFor(() => expect(screen.getByTestId('users-load-error')).toBeInTheDocument());

    expect(screen.getByText('Could not load the users. Check the connection.')).toBeInTheDocument();
    expect(screen.queryByText('No users yet')).not.toBeInTheDocument();
    expect(document.querySelectorAll('.row-stub').length).toBe(0);

    screen.getByRole('button', { name: 'Try again' }).click();

    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());

    expect(screen.queryByTestId('users-load-error')).not.toBeInTheDocument();
  });

  // The skeleton stands in for the list, so it wears the list's corner: rounded-lg, like OListDivided.
  it('draws the loading skeleton with the list radius', async () => {
    server.use(http.get('http://localhost/auth/users', () => new Promise<never>(() => {})));

    const pinia = setupWithPermissions(['users.read']);
    renderWithPlugins(UsersView, { pinia, global: { stubs } });

    await waitFor(() => expect(screen.getByTestId('users-loading')).toBeInTheDocument());

    expect(screen.getByTestId('users-loading')).toHaveClass('rounded-lg');
    expect(screen.getByTestId('users-loading')).not.toHaveClass('rounded-xl');
  });

  it('replaces the edited user row in place without refetching', async () => {
    let listRequests = 0;
    server.use(
      http.get('http://localhost/auth/users', ({ request }) => {
        if (new URL(request.url).searchParams.get('page')) listRequests += 1;

        return HttpResponse.json({ data: paginated([mockUser]) });
      })
    );

    const pinia = setupWithPermissions(['users.read', 'users.update']);
    renderWithPlugins(UsersView, { pinia, global: { stubs } });

    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());

    screen.getByRole('button', { name: 'edit' }).click();

    await waitFor(() => expect(document.querySelector('.form-submit')).toBeInTheDocument());

    (document.querySelector('.form-submit') as HTMLButtonElement).click();

    await waitFor(() => {
      expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
      expect(screen.getByText('Alicia Smith')).toBeInTheDocument();
    });

    expect(listRequests).toBe(1);
  });

  it('removes a deleted inactive account through the paginator, without refetching', async () => {
    server.use(
      http.get('http://localhost/auth/users', () => HttpResponse.json({ data: paginated([mockUser, inactiveUser]) })),
      http.delete('http://localhost/auth/users/:id', ({ params }) =>
        HttpResponse.json({ data: { ...inactiveUser, id: Number(params.id) } })
      )
    );

    const pinia = setupWithPermissions(['users.read', 'users.delete']);
    renderWithPlugins(UsersView, { pinia, global: { stubs } });

    await waitFor(() => expect(screen.getByText('Bob Jones')).toBeInTheDocument());

    screen.getByRole('button', { name: 'Delete' }).click();

    await waitFor(() => expect(screen.queryByText('Bob Jones')).not.toBeInTheDocument());

    expect(document.querySelector('.paginator-stub')?.getAttribute('data-total')).toBe('1');
  });
});
