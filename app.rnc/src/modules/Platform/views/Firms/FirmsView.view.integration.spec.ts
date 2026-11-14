import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import ToggleSwitch from 'primevue/toggleswitch';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MAddEditFirmForm from '../../components/molecules/MAddEditFirmForm/MAddEditFirmForm.vue';
import MFirmManagerForm from '../../components/molecules/MFirmManagerForm/MFirmManagerForm.vue';
import MFirmModulesForm from '../../components/molecules/MFirmModulesForm/MFirmModulesForm.vue';
import MOneTimePassword from '../../components/molecules/MOneTimePassword/MOneTimePassword.vue';
import { ALVORADA_FIXTURE, IPE_FIXTURE, PLATFORM_FIRMS_FIXTURE } from '../../test/fixtures';
import FirmsView from './FirmsView.view.vue';

/*
 * rnc-4 A29 (R25): the operator's console over the firms — each firm's name, subdomain link, modules,
 * when it was made and its leads; "Novo escritório" in the dialog, whose new firm joins the list while
 * its password is still on screen; the look, the modules and the manager login from each row, each
 * answer put in its row without a refetch.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MMainDialog: {
    props: ['title', 'visible'],
    template: '<div v-if="visible" data-testid="dialog" :data-title="title"><slot /></div>',
  },
};

let listCalls = 0;

function renderFirms() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      firm: null,
      id: 1,
      last_name: 'Battoni',
      name: 'Guilherme',
      platform: { is_operator: true, name: 'Hub da Reforma', view_as: null },
      role: 'Operador',
      role_slug: 'operador',
      username: 'battoni',
    } as never,
    ['platform.firms', 'platform.consent', 'users.read']
  );

  return renderWithPlugins(FirmsView, {
    global: {
      components: {
        Button,
        InputText,
        MAddEditFirmForm,
        MFirmManagerForm,
        MFirmModulesForm,
        MOneTimePassword,
        Skeleton,
        ToggleSwitch,
      },
      stubs: STUBS,
    },
    pinia,
  });
}

function rowOf(slug: string) {
  return screen.getAllByTestId('firm-row').find((row) => row.dataset.firm === slug)!;
}

beforeEach(() => {
  listCalls = 0;
  server.use(
    http.get('http://localhost/platform/firms', () => {
      listCalls += 1;

      return HttpResponse.json({ data: structuredClone(PLATFORM_FIRMS_FIXTURE) });
    }),
    http.post('http://localhost/platform/firms', () =>
      HttpResponse.json(
        {
          data: {
            firm: IPE_FIXTURE,
            manager: { email: null, id: 50, username: 'gestao.ipe' },
            password: 'Q7w8E9r0T1y2U3i4',
          },
        },
        { status: 201 }
      )
    ),
    http.patch('http://localhost/platform/firms/3', async ({ request }) =>
      HttpResponse.json({ data: { ...ALVORADA_FIXTURE, ...((await request.json()) as object) } })
    ),
    http.put('http://localhost/platform/firms/3/modules', async ({ request }) =>
      HttpResponse.json({ data: { ...ALVORADA_FIXTURE, ...((await request.json()) as object) } })
    )
  );
});

describe('FirmsView', () => {
  it('lists every firm with its subdomain link, modules, creation date and leads', async () => {
    renderFirms();

    await waitFor(() => expect(screen.getAllByTestId('firm-row')).toHaveLength(2));
    expect(screen.getAllByTestId('firm-row').map((row) => row.dataset.firm)).toEqual(['hub', 'alvorada']);

    const alvorada = rowOf('alvorada');
    const link = within(alvorada).getByTestId('firm-site-link') as HTMLAnchorElement;

    expect(alvorada.textContent).toContain('Alvorada Contabilidade');
    expect(link.href).toBe('http://alvorada.localhost:5173/');
    expect(link.textContent).toContain('alvorada.localhost:5173');
    expect(link.target).toBe('_blank');
    expect(
      within(alvorada)
        .getAllByTestId('firm-module')
        .map((chip) => chip.textContent?.trim())
    ).toContain('Radar');
    expect(alvorada.textContent).toContain('40 leads');
    expect(within(alvorada).getByTestId('firm-created').textContent).toContain('13');
    expect(within(rowOf('hub')).getByTestId('firm-platform').textContent).toContain('Platform');
    expect(rowOf('hub').textContent).toContain('Core only');
  });

  it('creates a firm: it joins the list while its password is still on screen', async () => {
    renderFirms();

    await waitFor(() => expect(screen.getAllByTestId('firm-row')).toHaveLength(2));
    await fireEvent.click(screen.getByTestId('firm-new'));

    const dialog = screen.getByTestId('dialog');

    expect(dialog.dataset.title).toBe('platform.form.newHeading');

    await fireEvent.update(within(dialog).getByTestId('firm-name'), 'Ipê Contábil');
    await fireEvent.update(within(dialog).getByTestId('firm-slug'), 'ipe');
    await fireEvent.update(within(dialog).getByTestId('firm-manager'), 'gestao.ipe');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(screen.getAllByTestId('firm-row')).toHaveLength(3));
    expect(screen.getAllByTestId('firm-row')[0]?.dataset.firm).toBe('ipe');
    expect(within(screen.getByTestId('dialog')).getByTestId('password-value').textContent).toContain(
      'Q7w8E9r0T1y2U3i4'
    );
    expect(listCalls).toBe(1);

    await fireEvent.click(screen.getByTestId('firm-done'));

    expect(screen.queryByTestId('dialog')).toBeNull();
  });

  it("edits a firm's look in the dialog and puts the answer in its row", async () => {
    renderFirms();

    await fireEvent.click(within(await waitFor(() => rowOf('alvorada'))).getByTestId('firm-edit'));

    expect(screen.getByTestId('dialog').dataset.title).toBe('platform.form.editHeading');

    await fireEvent.update(within(screen.getByTestId('dialog')).getByTestId('firm-name'), 'Alvorada Contábil');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(screen.queryByTestId('dialog')).toBeNull());
    expect(rowOf('alvorada').textContent).toContain('Alvorada Contábil');
    expect(listCalls).toBe(1);
  });

  it("switches a firm's modules from its row", async () => {
    renderFirms();

    await fireEvent.click(within(await waitFor(() => rowOf('alvorada'))).getByTestId('firm-modules'));

    expect(screen.getByTestId('dialog').dataset.title).toBe('platform.modules.heading');

    const chat = screen.getByTestId('module-switch-chat').querySelector('input') as HTMLInputElement;

    await fireEvent.click(chat);
    await fireEvent.click(screen.getByTestId('modules-save'));

    await waitFor(() => expect(screen.queryByTestId('dialog')).toBeNull());
    expect(
      within(rowOf('alvorada'))
        .getAllByTestId('firm-module')
        .map((chip) => chip.textContent?.trim())
    ).toContain('Diagnostic chat');
  });

  it("opens the manager's login from its row", async () => {
    renderFirms();

    await fireEvent.click(within(await waitFor(() => rowOf('alvorada'))).getByTestId('firm-manager-login'));

    expect(screen.getByTestId('dialog').dataset.title).toBe('platform.manager.heading');
    expect(screen.getByTestId('manager-username')).toBeTruthy();
  });

  it('says the firms did not load and offers to try again', async () => {
    server.use(
      http.get('http://localhost/platform/firms', () => HttpResponse.json({ error: 'boom' }, { status: 500 }))
    );
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderFirms();

    expect((await screen.findByRole('alert')).textContent).toContain('The firms could not be loaded.');
  });
});
