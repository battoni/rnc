import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import ConsentDefaultView from './ConsentDefaultView.view.vue';

/*
 * rnc-4 R25, R13, C15: the platform's default consent — the sentence the NEXT firm's version 1 copies.
 * It must name the firm through `{escritorio}`; existing firms keep their own versions.
 */
const DEFAULT_TEXT = 'Autorizo a {escritorio} a entrar em contato comigo sobre o diagnóstico.';
const sent: Record<string, unknown>[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

function renderConsent() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 1, last_name: 'Battoni', name: 'Guilherme', role: 'Operador', role_slug: 'operador', username: 'battoni' },
    ['platform.consent']
  );

  return renderWithPlugins(ConsentDefaultView, { global: { components: { Button, Skeleton, Textarea } }, pinia });
}

function textarea() {
  return screen.getByTestId('consent-text') as HTMLTextAreaElement;
}

beforeEach(() => {
  sent.length = 0;
  server.use(
    http.get('http://localhost/platform/consent-default', () => HttpResponse.json({ data: { text: DEFAULT_TEXT } })),
    http.put('http://localhost/platform/consent-default', async ({ request }) => {
      const body = (await request.json()) as { text: string };

      sent.push(body);

      return HttpResponse.json({ data: { text: body.text.trim() } });
    })
  );
});

describe('ConsentDefaultView', () => {
  it('opens with the current default sentence and says it reaches only the next firm', async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(DEFAULT_TEXT));
    expect(screen.getByTestId('consent-lede').textContent).toContain('next firm');
  });

  it('refuses a sentence without {escritorio} before sending it', async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(DEFAULT_TEXT));
    await fireEvent.update(textarea(), 'Autorizo o contato.');
    await fireEvent.click(screen.getByTestId('consent-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('consent-error').textContent).toContain('{escritorio}');
  });

  it('saves the sentence', async () => {
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(DEFAULT_TEXT));
    await fireEvent.update(textarea(), '  Autorizo a {escritorio} a me ligar.  ');
    await fireEvent.click(screen.getByTestId('consent-save'));

    await waitFor(() => expect(sent).toEqual([{ text: 'Autorizo a {escritorio} a me ligar.' }]));
    await waitFor(() => expect(textarea().value).toBe('Autorizo a {escritorio} a me ligar.'));
  });

  it("shows the API's refusal on the field", async () => {
    server.use(
      http.put('http://localhost/platform/consent-default', () =>
        HttpResponse.json(
          { errors: { text: ['platform.consentPlaceholderMissing'] }, message: 'platform.consentPlaceholderMissing' },
          { status: 422 }
        )
      )
    );
    renderConsent();

    await waitFor(() => expect(textarea().value).toBe(DEFAULT_TEXT));
    await fireEvent.click(screen.getByTestId('consent-save'));

    await waitFor(() => expect(screen.getByTestId('consent-error').textContent).toContain('{escritorio}'));
  });

  it('says the sentence did not load and offers to try again', async () => {
    server.use(
      http.get('http://localhost/platform/consent-default', () => HttpResponse.json({ error: 'boom' }, { status: 500 }))
    );
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderConsent();

    expect((await screen.findByRole('alert')).textContent).toContain('could not be loaded');
  });
});
