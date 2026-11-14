import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ConsentVersion } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import { MANAGER_FIXTURE } from '../../test/fixtures';
import FirmConsentView from './FirmConsentView.view.vue';

/*
 * rnc-4 A39 (R33, W79): the firm's consent — the current version's words, the history (version, date,
 * author), and a new version written with a live preview where `{escritorio}` becomes the firm's name.
 * A text without the placeholder is refused before sending, as the API refuses it.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const VERSION_ONE: ConsentVersion = {
  author: null,
  created_at: '2026-11-13T09:00:00-03:00',
  text: 'Autorizo a {escritorio} a entrar em contato comigo sobre o diagnóstico.',
  version: 1,
};

const VERSION_TWO: ConsentVersion = {
  author: { id: 30, name: 'Helena Alvorada' },
  created_at: '2026-11-14T10:00:00-03:00',
  text: 'Autorizo a {escritorio} a me ligar.',
  version: 2,
};

const sent: Record<string, unknown>[] = [];

function renderConsent() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(MANAGER_FIXTURE as never, ['firm.settings']);

  return renderWithPlugins(FirmConsentView, { global: { components: { Button, Skeleton, Textarea } }, pinia });
}

function textarea() {
  return screen.getByTestId('consent-text') as HTMLTextAreaElement;
}

beforeEach(() => {
  sent.length = 0;

  server.use(
    http.get('http://localhost/firm/consent-versions', () => HttpResponse.json({ data: [VERSION_TWO, VERSION_ONE] })),
    http.post('http://localhost/firm/consent-versions', async ({ request }) => {
      const body = (await request.json()) as { text: string };

      sent.push(body);

      return HttpResponse.json(
        {
          data: {
            author: { id: 30, name: 'Helena Alvorada' },
            created_at: '2026-11-14T12:00:00-03:00',
            text: body.text,
            version: 3,
          },
        },
        { status: 201 }
      );
    })
  );
});

describe('FirmConsentView', () => {
  it('shows the current version and the history with version, date and author', async () => {
    renderConsent();

    const rows = await screen.findAllByTestId('consent-version-row');

    expect(rows).toHaveLength(2);
    expect(screen.getByTestId('consent-current-version').textContent).toContain('2');
    expect(within(rows[0]!).getByTestId('consent-version-number').textContent).toContain('2');
    expect(rows[0]!.textContent).toContain('Helena Alvorada');
    expect(rows[0]!.textContent).toContain('11/14/2026');
    // The seeded v1 has no author: it came from the platform's default.
    expect(within(rows[1]!).getByTestId('consent-version-author').textContent).toContain('Platform');
    expect(textarea().value).toBe(VERSION_TWO.text);
  });

  it("previews the text with the firm's name where {escritorio} stands", async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(VERSION_TWO.text));

    await fireEvent.update(textarea(), 'Autorizo a {escritorio} a falar comigo.');

    expect(screen.getByTestId('consent-preview').textContent).toContain(
      'Autorizo a Alvorada Contabilidade a falar comigo.'
    );
  });

  it('refuses a text without {escritorio} before sending it', async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(VERSION_TWO.text));

    await fireEvent.update(textarea(), 'Autorizo o contato.');
    await fireEvent.click(screen.getByTestId('consent-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('consent-error').textContent).toContain('{escritorio}');
  });

  it('refuses saving the same words as the current version', async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(VERSION_TWO.text));

    await fireEvent.click(screen.getByTestId('consent-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('consent-error').textContent).toContain('same');
  });

  it('saves a new version and puts it at the head of the history', async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(VERSION_TWO.text));

    await fireEvent.update(textarea(), '  Autorizo a {escritorio} a me escrever.  ');
    await fireEvent.click(screen.getByTestId('consent-save'));

    await waitFor(() => expect(sent).toEqual([{ text: 'Autorizo a {escritorio} a me escrever.' }]));
    await waitFor(() => expect(screen.getAllByTestId('consent-version-row')).toHaveLength(3));
    expect(screen.getByTestId('consent-current-version').textContent).toContain('3');
  });

  it("shows the API's refusal on the field", async () => {
    server.use(
      http.post('http://localhost/firm/consent-versions', () =>
        HttpResponse.json(
          { errors: { text: ['leads.consentPlaceholderMissing'] }, message: 'leads.consentPlaceholderMissing' },
          { status: 422 }
        )
      )
    );

    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(VERSION_TWO.text));

    await fireEvent.update(textarea(), 'Autorizo a {escritorio} a me ligar já.');
    await fireEvent.click(screen.getByTestId('consent-save'));

    await waitFor(() => expect(screen.getByTestId('consent-error').textContent).toContain('{escritorio}'));
  });

  it('says the consent did not load and offers try again', async () => {
    server.use(
      http.get('http://localhost/firm/consent-versions', () => HttpResponse.json({ error: 'boom' }, { status: 500 }))
    );
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderConsent();

    expect((await screen.findByRole('alert')).textContent).toContain('could not be loaded');
  });
});
