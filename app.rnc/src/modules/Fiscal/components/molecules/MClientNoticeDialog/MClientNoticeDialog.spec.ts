import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ReadinessDetail } from '../../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { METALURGICA_DETAIL, NOTICE_EVENT, SEM_NOTAS_DETAIL } from '../../../test/fixtures';
import MClientNoticeDialog from './MClientNoticeDialog.vue';

/*
 * "Avisar cliente" (rnc-2 F8, R20, A17's Vitest half): the demo's e-mail to the client with its
 * counts filled in, "Copiar texto" and "Registrar aviso" — one POST, one timeline line with who and
 * when, shown in place and handed to the view. Nothing is sent: clients carry no e-mail (D12).
 */
const posted: string[] = [];
const writeText = vi.fn((_text: string) => Promise.resolve());

function renderNotice(client: ReadinessDetail = METALURGICA_DETAIL) {
  return renderWithPlugins(MClientNoticeDialog, { props: { client }, global: { components: { Button } } });
}

beforeEach(() => {
  posted.length = 0;
  writeText.mockClear();
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });

  server.use(
    http.post('http://localhost/fiscal/readiness/:id/notice', ({ params }) => {
      posted.push(String(params.id));

      return HttpResponse.json({ data: NOTICE_EVENT }, { status: 201 });
    })
  );
});

afterEach(() => Reflect.deleteProperty(navigator, 'clipboard'));

describe('MClientNoticeDialog', () => {
  it("writes the demo's e-mail with the client's counts and its emitting system", () => {
    renderNotice();

    const text = screen.getByTestId('notice-text').textContent ?? '';

    expect(text).toContain('Metalúrgica Boa Vista Ltda');
    expect(text).toContain('40');
    expect(text).toContain('70%');
    expect(text).toContain('1115');
    expect(text).toContain('ERP Delta 4.2');
    expect(screen.getByTestId('notice-flag').textContent).toBeTruthy();
  });

  it('asks a client without imported invoices for its XMLs instead of quoting counts', () => {
    renderNotice(SEM_NOTAS_DETAIL);

    const text = screen.getByTestId('notice-text').textContent ?? '';

    expect(text).toContain('Clínica Horizonte Ltda');
    expect(text).not.toContain('1115');
    expect(text).toContain('XML');
  });

  it('copies exactly the text on screen', async () => {
    renderNotice();

    await fireEvent.click(screen.getByTestId('notice-copy'));

    await waitFor(() => expect(writeText).toHaveBeenCalledTimes(1));

    const copied = writeText.mock.calls[0]?.[0] ?? '';

    expect(copied).toContain('Metalúrgica Boa Vista Ltda');
    expect(copied).toContain('ERP Delta 4.2');
    expect(copied.split('\n\n').length).toBeGreaterThan(3);
  });

  it('A17: "Registrar aviso" writes one timeline line, shows who and hands it to the view', async () => {
    const { emitted } = renderNotice();

    await fireEvent.click(screen.getByTestId('notice-register'));

    const line = await screen.findByTestId('notice-registered');

    expect(posted).toEqual(['7']);
    expect(line.textContent).toContain('Patrícia Lima');
    expect(emitted().onRegistered).toEqual([[NOTICE_EVENT]]);
    expect(screen.queryByTestId('notice-register')).toBeNull();
  });

  it('says the notice was not registered, and keeps the button to try again', async () => {
    server.use(
      http.post('http://localhost/fiscal/readiness/:id/notice', () =>
        HttpResponse.json({ message: 'boom' }, { status: 500 })
      )
    );

    const { emitted } = renderNotice();

    await fireEvent.click(screen.getByTestId('notice-register'));

    expect(await screen.findByRole('alert')).toBeTruthy();
    expect(screen.getByTestId('notice-register')).toBeTruthy();
    expect(emitted().onRegistered).toBeUndefined();
  });
});
