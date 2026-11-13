import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MImportClientsForm from './MImportClientsForm.vue';

/*
 * The portfolio's CSV import (rnc-1 F8, R25, A13): a refused file names each bad line and imports
 * nothing; a good one answers the counts. The template downloads the API's header row.
 */
let answer: () => Response;

function renderForm() {
  return renderWithPlugins(MImportClientsForm, { global: { components: { Button } } });
}

function chooseFile(content = 'nome;cnpj\n') {
  const input = screen.getByTestId('import-file') as HTMLInputElement;
  const file = new File([content], 'clientes.csv', { type: 'text/csv' });

  return fireEvent.change(input, { target: { files: [file] } });
}

beforeEach(() => {
  answer = () => HttpResponse.json({ data: { created: 3, total: 3, updated: 0 } });
  server.use(http.post('http://localhost/clients/import', () => answer()));
});

describe('MImportClientsForm', () => {
  it('asks for a file before sending', async () => {
    renderForm();

    await fireEvent.submit(screen.getByTestId('import-form'));

    expect(screen.getByTestId('import-error').textContent).toContain('Choose a CSV file');
  });

  it('names each refused line and says nothing was imported', async () => {
    answer = () =>
      HttpResponse.json(
        {
          code: 'import_invalid',
          errors: [
            { code: 'cnpj_invalid', field: 'cnpj', line: 3 },
            { code: 'required', field: 'nome', line: 5 },
          ],
          total_errors: 2,
        },
        { status: 422 }
      );

    const { emitted } = renderForm();

    await chooseFile();
    await fireEvent.submit(screen.getByTestId('import-form'));

    const lines = await screen.findAllByTestId('import-line-error');

    expect(screen.getByTestId('import-error').textContent).toContain('Nothing was imported');
    expect(lines.map((line) => line.getAttribute('data-line'))).toEqual(['3', '5']);
    expect(lines[0]!.textContent).toContain('Line 3');
    expect(lines[0]!.textContent).toContain('check digits');
    expect(lines[1]!.textContent).toContain('Name is blank');
    expect(emitted()['onSuccess']).toBeUndefined();
  });

  it('names the missing columns of a wrong header', async () => {
    answer = () => HttpResponse.json({ code: 'import_header', missing: ['faixa_faturamento'] }, { status: 422 });

    renderForm();

    await chooseFile();
    await fireEvent.submit(screen.getByTestId('import-form'));

    await waitFor(() => expect(screen.getByTestId('import-error').textContent).toContain('faixa_faturamento'));
  });

  it('answers the counts of a good file', async () => {
    const { emitted } = renderForm();

    await chooseFile();
    await fireEvent.submit(screen.getByTestId('import-form'));

    await waitFor(() => expect(emitted()['onSuccess']).toEqual([[{ created: 3, total: 3, updated: 0 }]]));
  });
});
