import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import { MNfeBatch, MNfeImportForm } from '../../components';
import { FOLDER_BATCH, UPLOAD_BATCH } from '../../test/fixtures';
import NfeImportView from './NfeImportView.view.vue';

/*
 * Reinaldo's "Notas fiscais" (rnc-2 F8, R15, R17): upload an XML or a ZIP, read the batch it made —
 * how many files, how many documents stored and every refusal by file with its reason — and page
 * through the firm's batches, the folder command's included. `fiscal.import` only (D14).
 */
let pages: unknown[][] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = { OPaginator: { props: ['total'], template: '<div data-testid="paginator">{{ total }}</div>' } };

function paginated(rows: unknown[], total = rows.length) {
  return { current_page: 1, data: rows, last_page: 1, per_page: 10, total };
}

function renderImports() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'ceo', username: 'reinaldo' },
    ['metrics.read', 'fiscal.import']
  );

  return renderWithPlugins(NfeImportView, {
    global: { components: { Button, MNfeBatch, MNfeImportForm, Skeleton }, stubs: STUBS },
    pinia,
  });
}

function chooseFile(name = 'notas.zip') {
  const input = screen.getByTestId('nfe-file') as HTMLInputElement;

  return fireEvent.change(input, { target: { files: [new File(['PK'], name, { type: 'application/zip' })] } });
}

beforeEach(() => {
  pages = [[FOLDER_BATCH]];
  server.use(
    http.get('http://localhost/fiscal/imports', () => HttpResponse.json({ data: paginated(pages[0]!) })),
    http.post('http://localhost/fiscal/imports', () => HttpResponse.json({ data: UPLOAD_BATCH }, { status: 201 }))
  );
});

describe('NfeImportView', () => {
  it("lists the firm's batches, the folder command's named as such", async () => {
    renderImports();

    const [batch] = await screen.findAllByTestId('nfe-batch');

    expect(batch!.getAttribute('data-source')).toBe('folder');
    expect(within(batch!).getByTestId('nfe-batch-stored').textContent).toContain('62');
    expect(within(batch!).queryAllByTestId('nfe-refusal')).toHaveLength(0);
  });

  it('shows the batch an upload made, with every refusal by file and its reason', async () => {
    renderImports();

    await screen.findAllByTestId('nfe-batch');
    await chooseFile();
    await fireEvent.submit(screen.getByTestId('nfe-import-form'));

    const result = await screen.findByTestId('nfe-result');
    const refusals = within(result).getAllByTestId('nfe-refusal');

    expect(within(result).getByTestId('nfe-batch-stored').textContent).toContain('2');
    expect(refusals.map((refusal) => refusal.getAttribute('data-code'))).toEqual(['not_xml', 'client_unknown']);
    expect(refusals[0]!.textContent).toContain('leia-me.xml');
    expect(refusals[0]!.textContent).not.toContain('not_xml');
  });

  it('adds the new batch on top of the list without reading it again', async () => {
    renderImports();

    await screen.findAllByTestId('nfe-batch');
    await chooseFile();
    await fireEvent.submit(screen.getByTestId('nfe-import-form'));

    await waitFor(() =>
      expect(
        within(screen.getByTestId('nfe-batches'))
          .getAllByTestId('nfe-batch')
          .map((batch) => batch.getAttribute('data-id'))
      ).toEqual(['41', '40'])
    );
  });

  it("keeps a listed batch's refusals folded until asked", async () => {
    pages = [[UPLOAD_BATCH]];

    renderImports();

    const [batch] = await screen.findAllByTestId('nfe-batch');

    expect(within(batch!).queryAllByTestId('nfe-refusal')).toHaveLength(0);

    await fireEvent.click(within(batch!).getByTestId('nfe-batch-toggle'));

    expect(within(batch!).getAllByTestId('nfe-refusal')).toHaveLength(2);
  });

  it('says the batches did not load, and offers to try again', async () => {
    server.use(
      http.get('http://localhost/fiscal/imports', () => HttpResponse.json({ message: 'boom' }, { status: 500 }))
    );

    renderImports();

    expect(await screen.findByRole('alert')).toBeTruthy();
  });
});
