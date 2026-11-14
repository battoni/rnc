import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { ALVORADA_FIXTURE, IPE_FIXTURE } from '../../../test/fixtures';
import MOneTimePassword from '../MOneTimePassword/MOneTimePassword.vue';
import MAddEditFirmForm from './MAddEditFirmForm.vue';

/*
 * rnc-4 A29 (R25, R11): the console's firm form. New: name, slug — with the address it will have, and
 * the API's format/reserved/taken refusals on the slug field — and the manager's login; the password
 * comes back once, with copy. Edit: the firm's look, with a live note on how white reads on the
 * primary and the API's `brand.lowContrast` on the colour field.
 */
const created: Record<string, unknown>[] = [];
const patched: Record<string, unknown>[] = [];
const writeText = vi.fn(() => Promise.resolve());

function renderForm(firm: typeof ALVORADA_FIXTURE | null = null) {
  return renderWithPlugins(MAddEditFirmForm, {
    props: { firm: firm ? structuredClone(firm) : null },
    global: { components: { Button, InputText, MOneTimePassword } },
  });
}

function field(testId: string) {
  return screen.getByTestId(testId) as HTMLInputElement;
}

beforeEach(() => {
  created.length = 0;
  patched.length = 0;
  writeText.mockClear();
  vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } });
  server.use(
    http.post('http://localhost/platform/firms', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      created.push(body);

      return HttpResponse.json(
        {
          data: {
            firm: IPE_FIXTURE,
            manager: { email: body.email, id: 50, username: body.manager },
            password: 'Q7w8E9r0T1y2U3i4',
          },
        },
        { status: 201 }
      );
    }),
    http.patch('http://localhost/platform/firms/:id', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      patched.push(body);

      return HttpResponse.json({ data: { ...ALVORADA_FIXTURE, ...body } });
    })
  );
});

afterEach(() => vi.unstubAllGlobals());

describe('MAddEditFirmForm — a new firm', () => {
  it('shows the address the slug will have, as it is typed', async () => {
    renderForm();

    expect(screen.queryByTestId('firm-primary')).toBeNull();

    await fireEvent.update(field('firm-slug'), 'ipe');

    expect(screen.getByTestId('firm-slug-preview').textContent).toContain(`ipe.${window.location.host}`);
  });

  it('creates the firm with its manager, then shows the password once, with copy', async () => {
    const { emitted } = renderForm();

    await fireEvent.update(field('firm-name'), '  Ipê Contábil ');
    await fireEvent.update(field('firm-slug'), ' IPE ');
    await fireEvent.update(field('firm-manager'), 'Gestao.Ipe');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(created).toHaveLength(1));
    expect(created[0]).toEqual({ email: null, manager: 'gestao.ipe', name: 'Ipê Contábil', slug: 'ipe' });

    expect((await screen.findByTestId('password-value')).textContent).toContain('Q7w8E9r0T1y2U3i4');
    expect(screen.getByTestId('firm-created').textContent).toContain('gestao.ipe');
    expect(emitted().onSuccess?.[0]).toEqual([IPE_FIXTURE]);

    await fireEvent.click(screen.getByTestId('password-copy'));

    expect(writeText).toHaveBeenCalledWith('Q7w8E9r0T1y2U3i4');
  });

  it('says what is missing before sending anything', async () => {
    renderForm();

    await fireEvent.click(screen.getByTestId('firm-save'));

    expect(created).toHaveLength(0);
    expect(screen.getByTestId('firm-name-error').textContent).toContain('Required');
    expect(screen.getByTestId('firm-slug-error').textContent).toContain('Required');
    expect(screen.getByTestId('firm-manager-error').textContent).toContain('Required');
  });

  it.each([
    ['firms.slugReserved', 'reserved'],
    ['firms.slugInvalid', 'lower-case letters'],
    ['firms.slugTaken', 'already has'],
  ])('shows the API refusal %s on the slug field', async (code, words) => {
    server.use(
      http.post('http://localhost/platform/firms', () =>
        HttpResponse.json({ errors: { slug: [code] }, message: code }, { status: 422 })
      )
    );
    renderForm();

    await fireEvent.update(field('firm-name'), 'Hub');
    await fireEvent.update(field('firm-slug'), 'www');
    await fireEvent.update(field('firm-manager'), 'gestao.www');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(screen.getByTestId('firm-slug-error').textContent).toContain(words));
    expect(screen.queryByTestId('password-value')).toBeNull();
  });
});

describe('MAddEditFirmForm — a firm’s look', () => {
  it("opens with the firm's own words, colours and proof points", () => {
    renderForm(ALVORADA_FIXTURE);

    expect(screen.queryByTestId('firm-slug')).toBeNull();
    expect(field('firm-name').value).toBe('Alvorada Contabilidade');
    expect(field('firm-logo-text').value).toBe('Alvorada Contabilidade');
    expect(field('firm-short-name').value).toBe('Alvorada');
    expect(field('firm-primary').value).toBe('#0f5c4d');
    expect((screen.getAllByTestId('firm-proof-value')[1] as HTMLInputElement).value).toBe('300+');
  });

  it('says, as the primary changes, whether white text reads on it', async () => {
    renderForm(ALVORADA_FIXTURE);

    const note = () => screen.getByTestId('firm-contrast-note');

    expect(note().dataset.readable).toBe('true');

    await fireEvent.update(field('firm-primary'), '#7fd3ff');

    expect(note().dataset.readable).toBe('false');
    expect(note().textContent).toContain('4.5:1');
  });

  it('shows the API’s contrast refusal on the colour field', async () => {
    server.use(
      http.patch('http://localhost/platform/firms/3', () =>
        HttpResponse.json(
          { errors: { brand_primary: ['brand.lowContrast'] }, message: 'brand.lowContrast' },
          { status: 422 }
        )
      )
    );
    renderForm(ALVORADA_FIXTURE);

    await fireEvent.update(field('firm-primary'), '#7fd3ff');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(screen.getByTestId('firm-primary-error').textContent).toContain('does not read'));
  });

  it('saves the look: trimmed words, lower-case colours, the four points', async () => {
    const { emitted } = renderForm(ALVORADA_FIXTURE);

    await fireEvent.update(field('firm-tagline'), '  Cedo e sem papel  ');
    await fireEvent.update(field('firm-primary'), '#0B4A3E');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(patched).toHaveLength(1));
    expect(patched[0]).toEqual({
      brand_accent: '#f4b63f',
      brand_primary: '#0b4a3e',
      logo_text: 'Alvorada Contabilidade',
      name: 'Alvorada Contabilidade',
      proof: ALVORADA_FIXTURE.proof,
      short_name: 'Alvorada',
      tagline: 'Cedo e sem papel',
    });
    await waitFor(() => expect(emitted().onSuccess).toHaveLength(1));
  });

  it('sends no proof when all four are empty, and refuses a half-filled set', async () => {
    renderForm(IPE_FIXTURE);

    await fireEvent.update(screen.getAllByTestId('firm-proof-value')[0]!, '10 anos');
    await fireEvent.click(screen.getByTestId('firm-save'));

    expect(patched).toHaveLength(0);
    expect(screen.getByTestId('firm-proof-error').textContent).toContain('four');

    await fireEvent.update(screen.getAllByTestId('firm-proof-value')[0]!, '');
    await fireEvent.click(screen.getByTestId('firm-save'));

    await waitFor(() => expect(patched).toHaveLength(1));
    expect(patched[0]?.proof).toBeNull();
  });
});
