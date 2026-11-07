import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { UPLOAD_BATCH } from '../../../test/fixtures';
import MNfeImportForm from './MNfeImportForm.vue';

/*
 * The NF-e upload (rnc-2 F8, R15, R17): one `.xml` or `.zip` of up to 20 MB. A file the API would
 * refuse whole — none, another kind, too big — is stopped here without a request; a request the API
 * refuses (422 kind or size, 429 throttle, anything else) is explained in the form. A batch that
 * ran — refusals and all — is handed to the view: a broken file never stops the others (R17).
 */
const MEGABYTE = 1024 * 1024;

let answer: () => Response;
let requests = 0;

function renderForm() {
  return renderWithPlugins(MNfeImportForm, { global: { components: { Button } } });
}

function chooseFile(name = 'notas.zip', size?: number) {
  const input = screen.getByTestId('nfe-file') as HTMLInputElement;
  const file = new File(['<nfeProc/>'], name, { type: name.endsWith('.zip') ? 'application/zip' : 'text/xml' });

  if (size !== undefined) Object.defineProperty(file, 'size', { value: size });

  return fireEvent.change(input, { target: { files: [file] } });
}

async function submit() {
  await fireEvent.submit(screen.getByTestId('nfe-import-form'));
}

beforeEach(() => {
  requests = 0;
  answer = () => HttpResponse.json({ data: UPLOAD_BATCH }, { status: 201 });
  server.use(
    http.post('http://localhost/fiscal/imports', () => {
      requests += 1;

      return answer();
    })
  );
});

describe('MNfeImportForm', () => {
  it('asks for a file before sending anything', async () => {
    renderForm();

    await submit();

    expect(screen.getByTestId('nfe-import-error').getAttribute('data-reason')).toBe('required');
    expect(requests).toBe(0);
  });

  it('stops a file that is neither .xml nor .zip, without a request', async () => {
    renderForm();

    await chooseFile('notas.pdf');
    await submit();

    expect(screen.getByTestId('nfe-import-error').getAttribute('data-reason')).toBe('kind');
    expect(requests).toBe(0);
  });

  it('stops a file over 20 MB, without a request', async () => {
    renderForm();

    await chooseFile('notas.zip', 21 * MEGABYTE);
    await submit();

    expect(screen.getByTestId('nfe-import-error').getAttribute('data-reason')).toBe('size');
    expect(screen.getByTestId('nfe-import-error').textContent).toContain('20 MB');
    expect(requests).toBe(0);
  });

  it("explains the API's 422 on the file", async () => {
    answer = () =>
      HttpResponse.json({ errors: { file: ['validation.extensions'] }, message: 'invalid' }, { status: 422 });

    const { emitted } = renderForm();

    await chooseFile('notas.xml');
    await submit();

    await waitFor(() => expect(screen.getByTestId('nfe-import-error').getAttribute('data-reason')).toBe('invalid'));

    expect(emitted().onImported).toBeUndefined();
  });

  it('asks to wait a minute when the import throttle answers 429', async () => {
    answer = () => HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 });

    renderForm();

    await chooseFile('notas.xml');
    await submit();

    await waitFor(() => expect(screen.getByTestId('nfe-import-error').getAttribute('data-reason')).toBe('throttled'));
  });

  it('says the import did not run when the API fails', async () => {
    answer = () => HttpResponse.json({ message: 'boom' }, { status: 500 });

    renderForm();

    await chooseFile('notas.xml');
    await submit();

    await waitFor(() => expect(screen.getByTestId('nfe-import-error').getAttribute('data-reason')).toBe('failed'));
  });

  it('hands the batch to the view, refusals included, and clears the file', async () => {
    const { emitted } = renderForm();

    await chooseFile('notas.zip');
    await submit();

    await waitFor(() => expect(emitted().onImported).toEqual([[UPLOAD_BATCH]]));

    expect(requests).toBe(1);
    expect(screen.queryByTestId('nfe-import-error')).toBeNull();
    expect((screen.getByTestId('nfe-file') as HTMLInputElement).value).toBe('');
  });
});
