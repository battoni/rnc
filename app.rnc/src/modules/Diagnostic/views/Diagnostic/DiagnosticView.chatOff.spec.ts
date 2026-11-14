import { ref } from 'vue';
import { screen } from '@testing-library/vue';
import Button from 'primevue/button';
import { describe, expect, it, vi } from 'vitest';
import { renderWithPlugins } from '@/test/render';
import DiagnosticView from './DiagnosticView.view.vue';

/*
 * rnc-4 R17: the chat receptionist is the `chat` module's. A firm without it — the platform firm's
 * root site among them — offers the diagnostic as the form only: no "Formulário · Conversar no chat"
 * toggle, so nobody starts a conversation the API would refuse (422 `modules.disabled`).
 *
 * Its own file because the host firm's answer is read once per page load and kept (`useFirmBrand`):
 * the main spec's firm has the chat, and this one is told it has none.
 */
const firm = ref<{ modules: string[]; name: string; slug: string } | null>(null);

vi.mock('@SiteModule', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  usePublicFirm: () => ({ firm, isBrandPending: ref(false), isFirmMissing: ref(false), load: () => Promise.resolve() }),
  useTracking: () => ({ track: vi.fn() }),
}));

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MChatReceptionist: true,
  MCnpjLookup: true,
  MDiagnosticProgress: true,
  RouterLink: { template: '<a><slot /></a>' },
  ThePublicLayout: { template: '<div><slot /></div>' },
};

function renderWith(modules: string[]) {
  firm.value = { modules, name: 'Hub da Reforma', slug: 'hub' };

  return renderWithPlugins(DiagnosticView, { global: { components: { Button }, stubs: STUBS } });
}

describe('DiagnosticView — the chat per firm (rnc-4 R17)', () => {
  it('offers no chat toggle where the firm has `chat` off', () => {
    renderWith([]);

    expect(screen.getByTestId('diagnostic')).toBeTruthy();
    expect(screen.queryByTestId('diagnostic-mode')).toBeNull();
    expect(screen.queryByTestId('mode-chat')).toBeNull();
  });

  it('offers it where the firm has `chat` on', () => {
    renderWith(['chat']);

    expect(screen.getByTestId('mode-chat')).toBeTruthy();
  });
});
