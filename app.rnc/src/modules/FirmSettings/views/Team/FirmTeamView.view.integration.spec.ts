import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Skeleton from 'primevue/skeleton';
import ToggleSwitch from 'primevue/toggleswitch';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { MOneTimePassword } from '@PlatformModule';
import { useUserStore } from '@UserModule';
import MAddEditTeamMemberForm from '../../components/molecules/MAddEditTeamMemberForm/MAddEditTeamMemberForm.vue';
import MTeamPasswordForm from '../../components/molecules/MTeamPasswordForm/MTeamPasswordForm.vue';
import { ALL_ROLES_FIXTURE, MANAGER_FIXTURE, TEAM_FIXTURE } from '../../test/fixtures';
import FirmTeamView from './FirmTeamView.view.vue';

/*
 * rnc-4 A39 (R31): the firm's team logins for its manager — the list (the API scopes it to the firm),
 * a new login, an edit, deactivate/reactivate and a password reset shown once. Each answer replaces its
 * row; nothing refetches the list.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

// The confirm popup's own behaviour is PrimeVue's; here "yes" is answered at once.
vi.mock('primevue/useconfirm', () => ({
  useConfirm: () => ({ require: ({ accept }: { accept: () => void }) => accept() }),
}));

const STUBS = {
  MMainDialog: {
    props: ['title', 'visible'],
    template: '<div v-if="visible" data-testid="dialog" :data-title="title"><slot /></div>',
  },
};

const MANAGER_KEYS = [
  'firm.settings',
  'users.read',
  'users.create',
  'users.update',
  'users.archive',
  'users.reset_password',
];

let listCalls = 0;
const patched: { body: Record<string, unknown>; url: string }[] = [];

function renderTeam(keys: string[] = MANAGER_KEYS) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(MANAGER_FIXTURE as never, keys);

  return renderWithPlugins(FirmTeamView, {
    global: {
      components: {
        Button,
        InputText,
        MAddEditTeamMemberForm,
        MOneTimePassword,
        MTeamPasswordForm,
        RadioButton,
        Skeleton,
        ToggleSwitch,
      },
      stubs: STUBS,
    },
    pinia,
  });
}

function rowOf(username: string) {
  return screen.getAllByTestId('team-row').find((row) => row.dataset.username === username)!;
}

beforeEach(() => {
  listCalls = 0;
  patched.length = 0;

  server.use(
    http.get('http://localhost/auth/users', () => {
      listCalls += 1;

      return HttpResponse.json({
        data: { current_page: 1, data: TEAM_FIXTURE, last_page: 1, per_page: 15, total: TEAM_FIXTURE.length },
      });
    }),
    http.get('http://localhost/auth/roles', () => HttpResponse.json({ data: ALL_ROLES_FIXTURE })),
    http.patch('http://localhost/auth/users/:id/archive', ({ params }) =>
      HttpResponse.json({
        data: { ...TEAM_FIXTURE.find((user) => user.id === Number(params.id)), status: 'inactive' },
      })
    ),
    http.patch('http://localhost/auth/users/:id', async ({ params, request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      patched.push({ body, url: request.url });

      return HttpResponse.json({ data: TEAM_FIXTURE.find((user) => user.id === Number(params.id)) });
    })
  );
});

describe('FirmTeamView', () => {
  it('lists the firm’s logins with role, specialist mark and status', async () => {
    renderTeam();

    await waitFor(() => expect(screen.getAllByTestId('team-row')).toHaveLength(3));

    expect(rowOf('equipe.alvorada').textContent).toContain('Equipe');
    expect(within(rowOf('bruno.alvorada')).getByTestId('team-specialist')).toBeTruthy();
    expect(within(rowOf('equipe.alvorada')).queryByTestId('team-specialist')).toBeNull();
  });

  it('opens a new login with only the two team roles offered', async () => {
    renderTeam();

    await waitFor(() => expect(screen.getAllByTestId('team-row')).toHaveLength(3));

    await fireEvent.click(screen.getByTestId('team-new'));

    await waitFor(() =>
      expect(screen.getAllByTestId('member-role-option').map((option) => option.textContent?.trim())).toEqual([
        'Equipe',
        'Gestão',
      ])
    );
  });

  it('deactivates a login and puts the answer in its row, with no refetch', async () => {
    renderTeam();

    await waitFor(() => expect(screen.getAllByTestId('team-row')).toHaveLength(3));

    await fireEvent.click(within(rowOf('equipe.alvorada')).getByTestId('team-toggle'));

    await waitFor(() =>
      expect(within(rowOf('equipe.alvorada')).getByTestId('team-status').textContent).toContain('Inactive')
    );
    expect(listCalls).toBe(1);
  });

  it('resets a password and shows it once', async () => {
    renderTeam();

    await waitFor(() => expect(screen.getAllByTestId('team-row')).toHaveLength(3));

    await fireEvent.click(within(rowOf('equipe.alvorada')).getByTestId('team-reset'));
    await fireEvent.click(screen.getByTestId('reset-generate'));

    await waitFor(() => expect(patched).toHaveLength(1));

    expect(patched[0]!.url).toMatch(/\/auth\/users\/31$/);
    expect(Object.keys(patched[0]!.body)).toEqual(['password']);
    await waitFor(() =>
      expect(screen.getByTestId('password-value').textContent?.trim()).toBe(patched[0]!.body.password)
    );
  });

  it('hides what the person may not do', async () => {
    renderTeam(['firm.settings', 'users.read']);

    await waitFor(() => expect(screen.getAllByTestId('team-row')).toHaveLength(3));

    expect(screen.queryByTestId('team-new')).toBeNull();
    expect(screen.queryByTestId('team-reset')).toBeNull();
    expect(screen.queryByTestId('team-toggle')).toBeNull();
    expect(screen.queryByTestId('team-edit')).toBeNull();
  });

  it('says the team did not load and offers try again', async () => {
    server.use(http.get('http://localhost/auth/users', () => HttpResponse.json({ error: 'boom' }, { status: 500 })));
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderTeam();

    expect((await screen.findByRole('alert')).textContent).toContain('could not be loaded');
  });
});
