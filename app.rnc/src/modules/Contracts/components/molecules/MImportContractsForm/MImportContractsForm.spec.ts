import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MImportContractsForm from './MImportContractsForm.vue';

/*
 * The contracts CSV (rnc-3 F7, R18): UNLIKE the clients CSV, lines are independent — the good ones
 * are stored and the bad ones come back by line in a 200, so the form says how many went in and
 * names each line left out, translated from its code; a file refused whole (422 `import_*`) says
 * why; the template downloads the API's header row. Each answer is handed to the view, which reads
 * the alert again.
 */
let answer: () => Response;
let templateRequests = 0;

function renderForm() {
  return renderWithPlugins(MImportContractsForm, { global: { components: { Button } } });
}

function chooseFile(content = 'cnpj_cliente;tipo\n') {
  const input = screen.getByTestId('contracts-import-file') as HTMLInputElement;
  const file = new File([content], 'contratos.csv', { type: 'text/csv' });

  return fireEvent.change(input, { target: { files: [file] } });
}

beforeEach(() => {
  templateRequests = 0;
  answer = () => HttpResponse.json({ data: { created: 3, errors: [], total: 3, total_errors: 0, updated: 0 } });
  server.use(
    http.post('http://localhost/contracts/import', () => answer()),
    http.get('http://localhost/contracts/template', () => {
      templateRequests++;

      return new HttpResponse('cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma\n', {
        headers: { 'Content-Type': 'text/csv; charset=UTF-8' },
      });
    })
  );
});

describe('MImportContractsForm', () => {
  it('shows the columns the API reads', () => {
    renderForm();

    expect(screen.getByTestId('contracts-import-columns').textContent).toBe(
      'cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma'
    );
  });

  it('asks for a file before sending', async () => {
    renderForm();

    await fireEvent.submit(screen.getByTestId('contracts-import-form'));

    expect(screen.getByTestId('contracts-import-error').textContent).toContain('Choose a CSV file');
  });

  it('hands a clean import to the view', async () => {
    const { emitted } = renderForm();

    await chooseFile();
    await fireEvent.submit(screen.getByTestId('contracts-import-form'));

    await waitFor(() =>
      expect(emitted()['onImported']).toEqual([[{ created: 3, errors: [], total: 3, total_errors: 0, updated: 0 }]])
    );
    expect(screen.queryByTestId('contracts-import-line-error')).toBeNull();
  });

  it('R18: stores the good lines and names each bad one by its line, without stopping the others', async () => {
    answer = () =>
      HttpResponse.json({
        data: {
          created: 2,
          errors: [
            { code: 'client_unknown', field: 'cnpj_cliente', line: 3 },
            { code: 'year_invalid', field: 'inicio', line: 5 },
            { code: 'end_before_start', field: 'fim', line: 6 },
          ],
          total: 6,
          total_errors: 4,
          updated: 1,
        },
      });

    const { emitted } = renderForm();

    await chooseFile();
    await fireEvent.submit(screen.getByTestId('contracts-import-form'));

    const lines = await screen.findAllByTestId('contracts-import-line-error');

    expect(screen.getByTestId('contracts-import-partial').textContent).toContain(
      '3 contracts stored: 2 new and 1 updated'
    );
    expect(lines.map((line) => line.getAttribute('data-line'))).toEqual(['3', '5', '6']);
    expect(lines[0]!.textContent).toContain('Line 3');
    expect(lines[0]!.textContent).toContain('no client of the firm has this CNPJ');
    expect(lines[1]!.textContent).toContain('Start: year outside 2000–2100');
    expect(lines[2]!.textContent).toContain('End: ends before the start');
    expect(screen.getByTestId('contracts-import-more').textContent).toContain('And 1 more line');
    expect(emitted()['onImported']).toHaveLength(1);
  });

  it('says why a file was refused whole', async () => {
    answer = () =>
      HttpResponse.json(
        { code: 'import_header', message: 'portfolio.import.header', missing: ['fim'] },
        { status: 422 }
      );

    const { emitted } = renderForm();

    await chooseFile();
    await fireEvent.submit(screen.getByTestId('contracts-import-form'));

    await waitFor(() =>
      expect(screen.getByTestId('contracts-import-error').textContent).toContain('Missing columns: fim')
    );
    expect(emitted()['onImported']).toBeUndefined();
  });

  it("downloads the API's template", async () => {
    const original = { create: URL.createObjectURL, revoke: URL.revokeObjectURL };
    const createObjectURL = vi.fn((_blob: Blob) => 'blob:template');
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

    URL.createObjectURL = createObjectURL;
    URL.revokeObjectURL = vi.fn();
    renderForm();

    await fireEvent.click(screen.getByTestId('contracts-import-template'));

    await waitFor(() => expect(createObjectURL).toHaveBeenCalledTimes(1));
    expect(templateRequests).toBe(1);
    expect(click).toHaveBeenCalledTimes(1);

    URL.createObjectURL = original.create;
    URL.revokeObjectURL = original.revoke;
    click.mockRestore();
  });
});
