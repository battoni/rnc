import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MAddEditBrandForm from '../../components/molecules/MAddEditBrandForm/MAddEditBrandForm.vue';
import { BRAND_LIST_FIXTURE, EXPRESS_FIXTURE, PRIME_FIXTURE } from '../../test/fixtures';
import BrandsView from './BrandsView.view.vue';

/*
 * The brands console (rnc-3 F2, R5) for the operator: Franco's own row ("Marca principal") and the
 * sub-brands, each with its lead count; the edit in the dialog; the switch; and A6's "Ver como", which
 * opens the public site under the brand in a new tab as a preview — disabled "Inativa" when the brand
 * is off (AM12), since the site would only fall back to Franco.
 */
const toggled: string[] = [];
const open = vi.fn();

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
};

function renderBrands() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 1, last_name: 'Battoni', name: 'Guilherme', role: 'Operador', role_slug: 'operador', username: 'operador' },
    ['brands.manage', 'users.read']
  );

  return renderWithPlugins(BrandsView, {
    global: { components: { Button, InputText, MAddEditBrandForm, Skeleton }, stubs: STUBS },
    pinia,
  });
}

function rowOf(slug: string) {
  return screen.getAllByTestId('brand-row').find((row) => row.dataset.brand === slug)!;
}

beforeEach(() => {
  toggled.length = 0;
  open.mockClear();
  vi.stubGlobal('open', open);
  server.use(
    http.get('http://localhost/brands', () => HttpResponse.json({ data: structuredClone(BRAND_LIST_FIXTURE) })),
    http.post('http://localhost/brands/:id/:action', ({ params }) => {
      toggled.push(`${params.id}/${params.action}`);
      const brand = Number(params.id) === EXPRESS_FIXTURE.id ? EXPRESS_FIXTURE : PRIME_FIXTURE;

      return HttpResponse.json({ data: { ...brand, active: params.action === 'activate' } });
    }),
    http.patch('http://localhost/brands/1', async ({ request }) =>
      HttpResponse.json({ data: { ...EXPRESS_FIXTURE, ...((await request.json()) as object) } })
    )
  );
});

afterEach(() => vi.unstubAllGlobals());

describe('BrandsView', () => {
  it("lists Franco's main brand first, then the sub-brands, each with its lead count", async () => {
    renderBrands();

    const main = await screen.findByTestId('brand-main');

    expect(main.textContent).toContain('Main brand');
    expect(main.textContent).toContain('Franco Contabilidade');
    expect(main.textContent).toContain('30 leads');
    expect(screen.getAllByTestId('brand-row').map((row) => row.dataset.brand)).toEqual(['express', 'prime']);
    expect(rowOf('express').textContent).toContain('Franco Express Contabilidade');
    expect(rowOf('express').textContent).toContain('6 leads');
    expect(rowOf('express').textContent).toContain('Active');
    expect(rowOf('prime').textContent).toContain('Inactive');
  });

  it('opens the site under the brand, as a preview, in a new tab (A6)', async () => {
    renderBrands();

    await fireEvent.click(within(await waitFor(() => rowOf('express'))).getByTestId('brand-view-as'));

    expect(within(rowOf('express')).getByTestId('brand-view-as').textContent).toContain('View as Franco Express');
    expect(open).toHaveBeenCalledWith('/?marca=express&previa=1', '_blank', 'noopener');
  });

  it('disables "Ver como" for an inactive brand, saying so (AM12)', async () => {
    renderBrands();

    const button = within(await waitFor(() => rowOf('prime'))).getByTestId('brand-view-as') as HTMLButtonElement;

    expect(button.disabled).toBe(true);
    expect(button.textContent).toContain('Inactive');

    await fireEvent.click(button);

    expect(open).not.toHaveBeenCalled();
  });

  it('deactivates and reactivates a brand from its row, no refetch', async () => {
    renderBrands();

    await fireEvent.click(within(await waitFor(() => rowOf('express'))).getByTestId('brand-toggle'));

    await waitFor(() => expect(rowOf('express').dataset.active).toBe('false'));
    expect(toggled).toEqual(['1/deactivate']);

    await fireEvent.click(within(rowOf('prime')).getByTestId('brand-toggle'));

    await waitFor(() => expect(rowOf('prime').dataset.active).toBe('true'));
    expect(toggled).toEqual(['1/deactivate', '2/activate']);
  });

  it('edits a brand in the dialog and puts the answer in its row', async () => {
    renderBrands();

    await fireEvent.click(within(await waitFor(() => rowOf('express'))).getByTestId('brand-edit'));
    await fireEvent.update(within(screen.getByTestId('dialog')).getByTestId('brand-tagline'), 'Rápida e sem papel');
    await fireEvent.click(screen.getByTestId('brand-save'));

    await waitFor(() => expect(screen.queryByTestId('dialog')).toBeNull());
    expect(rowOf('express').textContent).toContain('Rápida e sem papel');
  });

  it('says the brands did not load and offers to try again', async () => {
    server.use(http.get('http://localhost/brands', () => HttpResponse.json({ error: 'boom' }, { status: 500 })));
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderBrands();

    expect((await screen.findByRole('alert')).textContent).toContain('The brands could not be loaded.');
  });
});
