import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { BETA_CONSENT_FIXTURE } from '../../../test/fixtures';
import MBetaSignupForm from './MBetaSignupForm.vue';

/*
 * rnc-4 A33 (R29): the root site's "join the beta" form. It shows the consent words the API hands it
 * (`GET public/beta-signups/consent`), unticked; it refuses each empty or malformed field on the field
 * and sends nothing; it sends the firm's answers with the visit's source; a 201 is the success state
 * and a 422 puts the API's refusals on the fields it names.
 */
const sent: Record<string, unknown>[] = [];

function renderForm() {
  return renderWithPlugins(MBetaSignupForm, {
    global: { components: { Button, Checkbox, InputText, Select, Skeleton, Textarea } },
  });
}

function answerSignup(status: number, body: Record<string, unknown>) {
  server.use(
    http.post('http://localhost/public/beta-signups', async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>);

      return HttpResponse.json(body, { status });
    })
  );
}

async function pickUf(name: string) {
  await fireEvent.click(screen.getByTestId('beta-uf'));
  // PrimeVue's Select picks an option on `mousedown`, not on `click`.
  await fireEvent.mouseDown(await screen.findByRole('option', { name }));
}

async function fillValidForm() {
  await screen.findByTestId('beta-consent-text');
  await fireEvent.update(screen.getByTestId('beta-company'), '  Contábil Horizonte  ');
  await fireEvent.update(screen.getByTestId('beta-name'), 'Marina Duarte');
  await fireEvent.update(screen.getByTestId('beta-role'), 'Sócia');
  await fireEvent.update(screen.getByTestId('beta-email'), ' Marina@Horizonte.example ');
  await fireEvent.update(screen.getByTestId('beta-phone'), '(31) 98888-7777');
  await fireEvent.update(screen.getByTestId('beta-city'), 'Belo Horizonte');
  await pickUf('Minas Gerais');
  await fireEvent.click(screen.getByTestId('beta-band-de_101_a_300'));
  await fireEvent.update(screen.getByTestId('beta-message'), 'Queremos começar em janeiro.');
  await fireEvent.click(screen.getByTestId('beta-consent').querySelector('input')!);
}

beforeEach(() => {
  sent.length = 0;
  sessionStorage.clear();
  server.use(
    http.get('http://localhost/public/beta-signups/consent', () => HttpResponse.json({ data: BETA_CONSENT_FIXTURE }))
  );
});

describe('MBetaSignupForm', () => {
  it("shows the API's consent words, unticked", async () => {
    renderForm();

    expect((await screen.findByTestId('beta-consent-text')).textContent).toContain(BETA_CONSENT_FIXTURE.text);
    expect(screen.getByTestId('beta-consent').querySelector('input')!.checked).toBe(false);
  });

  it('refuses every empty field and the missing consent on the field, and sends nothing', async () => {
    answerSignup(201, { data: { channel: 'beta', company: 'x' } });
    renderForm();
    await screen.findByTestId('beta-consent-text');

    await fireEvent.click(screen.getByTestId('beta-submit'));

    for (const field of ['company', 'name', 'role', 'email', 'phone', 'city', 'uf', 'clients_band', 'consent']) {
      expect(screen.getByTestId(`beta-error-${field}`).textContent?.trim()).not.toBe('');
    }

    expect(screen.queryByTestId('beta-error-message')).toBeNull();
    expect(sent).toHaveLength(0);
  });

  it('refuses an e-mail and a phone that cannot be right', async () => {
    renderForm();
    await screen.findByTestId('beta-consent-text');

    await fireEvent.update(screen.getByTestId('beta-email'), 'marina@');
    await fireEvent.update(screen.getByTestId('beta-phone'), '9888');
    await fireEvent.click(screen.getByTestId('beta-submit'));

    expect(screen.getByTestId('beta-error-email').textContent).toContain('valid e-mail');
    expect(screen.getByTestId('beta-error-phone').textContent).toContain('area code');
  });

  it("sends the firm's answers with the visit's source and shows the success state on 201", async () => {
    answerSignup(201, { data: { channel: 'beta', company: 'Contábil Horizonte' } });
    const { emitted } = renderForm();

    await fillValidForm();
    await fireEvent.click(screen.getByTestId('beta-submit'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({
      city: 'Belo Horizonte',
      clients_band: 'de_101_a_300',
      company: 'Contábil Horizonte',
      consent: true,
      email: 'marina@horizonte.example',
      medium: null,
      message: 'Queremos começar em janeiro.',
      name: 'Marina Duarte',
      phone: '(31) 98888-7777',
      role: 'Sócia',
      source: 'direto',
      uf: 'MG',
    });
    expect((await screen.findByTestId('beta-success')).textContent).toContain('Contábil Horizonte');
    expect(screen.queryByTestId('beta-submit')).toBeNull();
    expect(emitted().onSuccess).toHaveLength(1);
  });

  it("puts the API's refusals on the fields it names on 422", async () => {
    answerSignup(422, {
      errors: { email: ['validation.email'], phone: ['validation.regex'] },
      message: 'The given data was invalid.',
    });
    renderForm();

    await fillValidForm();
    await fireEvent.click(screen.getByTestId('beta-submit'));

    expect((await screen.findByTestId('beta-error-email')).textContent).toContain('valid e-mail');
    expect(screen.getByTestId('beta-error-phone').textContent).toContain('area code');
    expect(screen.queryByTestId('beta-success')).toBeNull();
  });

  it('says to wait a minute when the API is asked too often (429)', async () => {
    answerSignup(429, { message: 'Too Many Attempts.' });
    renderForm();

    await fillValidForm();
    await fireEvent.click(screen.getByTestId('beta-submit'));

    expect((await screen.findByTestId('beta-form-error')).textContent).toContain('minute');
  });

  it('offers to try again when the consent words did not load, and asks nothing without them', async () => {
    server.use(http.get('http://localhost/public/beta-signups/consent', () => HttpResponse.json({}, { status: 500 })));
    renderForm();

    expect(await screen.findByTestId('beta-consent-failed')).toBeTruthy();
    expect(screen.queryByTestId('beta-submit')).toBeNull();

    server.use(
      http.get('http://localhost/public/beta-signups/consent', () => HttpResponse.json({ data: BETA_CONSENT_FIXTURE }))
    );
    await fireEvent.click(screen.getByTestId('beta-consent-retry'));

    expect(await screen.findByTestId('beta-consent-text')).toBeTruthy();
  });
});
