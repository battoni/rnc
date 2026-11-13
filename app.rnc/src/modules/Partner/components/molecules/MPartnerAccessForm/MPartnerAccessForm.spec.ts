import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Partner } from '../../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { PARTNER_ACCESS_FIXTURE, PARTNER_FIXTURE } from '../../../test/fixtures';
import MPartnerAccessForm from './MPartnerAccessForm.vue';

/*
 * A partner's portal login (rnc-2 F1, R2) from the registry: create it with a username and an
 * e-mail, regenerate its password or revoke it. A generated password is shown ONCE, with a copy
 * button, and never handed to the view; the API's refusals are shown in the form.
 */
const PASSWORD = 'Kd83hfPq02LxZm4a';

const sent: { body: unknown; method: string; url: string }[] = [];
const writeText = vi.fn(() => Promise.resolve());

function renderForm(partner: Partner = PARTNER_FIXTURE) {
  return renderWithPlugins(MPartnerAccessForm, { props: { partner }, global: { components: { Button, InputText } } });
}

function record(request: Request, body: unknown = null) {
  sent.push({ body, method: request.method, url: new URL(request.url).pathname });
}

beforeEach(() => {
  sent.length = 0;
  writeText.mockClear();
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });

  server.use(
    http.post('http://localhost/partners/3/access', async ({ request }) => {
      record(request, await request.json());

      return HttpResponse.json({ data: { ...PARTNER_ACCESS_FIXTURE, password: PASSWORD } }, { status: 201 });
    }),
    http.post('http://localhost/partners/3/access/password', ({ request }) => {
      record(request);

      return HttpResponse.json({ data: { ...PARTNER_ACCESS_FIXTURE, password: 'NewPass0000000002' } });
    }),
    http.delete('http://localhost/partners/3/access', ({ request }) => {
      record(request);

      return HttpResponse.json({
        data: { ...PARTNER_ACCESS_FIXTURE, active: false, revoked_at: '2026-11-10T10:00:00+00:00' },
      });
    })
  );
});

afterEach(() => Reflect.deleteProperty(navigator, 'clipboard'));

describe('MPartnerAccessForm — no login yet', () => {
  it('creates the login and shows the password once, with a copy button', async () => {
    const { emitted } = renderForm();

    await fireEvent.update(screen.getByTestId('access-username'), ' Parceiro.3 ');
    await fireEvent.update(screen.getByTestId('access-email'), 'portal3@parceiro.exemplo');
    await fireEvent.submit(screen.getByTestId('access-form'));

    expect((await screen.findByTestId('access-password')).textContent).toContain(PASSWORD);
    expect(sent).toEqual([
      {
        body: { email: 'portal3@parceiro.exemplo', username: 'parceiro.3' },
        method: 'POST',
        url: '/partners/3/access',
      },
    ]);

    await fireEvent.click(screen.getByTestId('access-copy'));
    expect(writeText).toHaveBeenCalledWith(PASSWORD);

    // The view gets the login, never the password.
    expect(emitted()['onChanged']).toEqual([[PARTNER_ACCESS_FIXTURE]]);
  });

  it('prefills the e-mail with the partner registry address', () => {
    renderForm();

    expect((screen.getByTestId('access-email') as HTMLInputElement).value).toBe('contato3@parceiro.exemplo');
  });

  it('asks for the username before sending anything', async () => {
    renderForm();

    await fireEvent.submit(screen.getByTestId('access-form'));

    expect(screen.getByTestId('access-form').textContent).toContain('Required.');
    expect(sent).toEqual([]);
  });

  it('shows the field the API refused', async () => {
    server.use(
      http.post('http://localhost/partners/3/access', () =>
        HttpResponse.json({ errors: { username: ['validation.unique'] }, message: 'x' }, { status: 422 })
      )
    );
    renderForm();

    await fireEvent.update(screen.getByTestId('access-username'), 'reinaldo');
    await fireEvent.submit(screen.getByTestId('access-form'));

    await waitFor(() => expect(screen.getByTestId('access-username').getAttribute('aria-invalid')).toBe('true'));
    expect(screen.queryByTestId('access-password')).toBeNull();
  });

  it('names the second active login refusal (409)', async () => {
    server.use(
      http.post('http://localhost/partners/3/access', () =>
        HttpResponse.json({ code: 'partner_access_exists', error: 'partners.access.exists' }, { status: 409 })
      )
    );
    renderForm();

    await fireEvent.update(screen.getByTestId('access-username'), 'parceiro.3');
    await fireEvent.submit(screen.getByTestId('access-form'));

    await waitFor(() => expect(screen.getByTestId('access-form').textContent).toContain('already has an active login'));
  });
});

describe('MPartnerAccessForm — an active login', () => {
  const WITH_ACCESS: Partner = { ...PARTNER_FIXTURE, access: PARTNER_ACCESS_FIXTURE };

  it('shows the login, and regenerates its password once', async () => {
    const { emitted } = renderForm(WITH_ACCESS);

    expect(screen.getByTestId('access-current').textContent).toContain('parceiro.3');
    expect(screen.queryByTestId('access-form')).toBeNull();

    await fireEvent.click(screen.getByTestId('access-regenerate'));

    expect((await screen.findByTestId('access-password')).textContent).toContain('NewPass0000000002');
    expect(sent.map((call) => [call.method, call.url])).toEqual([['POST', '/partners/3/access/password']]);
    expect(emitted()['onChanged']).toEqual([[PARTNER_ACCESS_FIXTURE]]);
  });

  it('revokes it and offers a new one', async () => {
    const { emitted } = renderForm(WITH_ACCESS);

    await fireEvent.click(screen.getByTestId('access-revoke'));

    await screen.findByTestId('access-form');
    expect(sent.map((call) => [call.method, call.url])).toEqual([['DELETE', '/partners/3/access']]);
    expect((emitted()['onChanged'] as [{ active: boolean }][])[0]![0].active).toBe(false);
    expect((screen.getByTestId('access-username') as HTMLInputElement).value).toBe('parceiro.3');
  });
});
