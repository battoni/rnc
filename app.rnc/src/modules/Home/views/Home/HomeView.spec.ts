import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import HomeView from './HomeView.view.vue';

const replace = vi.fn();

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRouter: () => ({ replace }),
}));

beforeEach(() => replace.mockClear());

const RouterLinkStub = { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' };

function renderAs(permissions: string[]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 1, name: 'Reinaldo', last_name: 'Franco', username: 'reinaldo', role: 'CEO', role_slug: 'ceo' },
    permissions
  );

  return renderWithPlugins(HomeView, { pinia, global: { stubs: { RouterLink: RouterLinkStub } } });
}

describe('HomeView', () => {
  it('greets the person by name', () => {
    const { getByText } = renderAs(['home.read', 'leads.read']);

    expect(getByText('Hello, Reinaldo')).toBeTruthy();
  });

  it('offers the team only its own workspace', () => {
    const { queryAllByTestId } = renderAs(['home.read', 'leads.read']);

    expect(queryAllByTestId('workspace-card').map((card) => card.dataset.workspace)).toEqual(['team']);
  });

  it('offers Reinaldo the team and his own workspace', () => {
    const { queryAllByTestId } = renderAs(['home.read', 'leads.read', 'metrics.read']);

    expect(queryAllByTestId('workspace-card').map((card) => card.dataset.workspace)).toEqual(['team', 'ceo']);
  });

  // rnc-2 A4: every refused back-office route falls back to Home, so this is where a partner bounces.
  it('sends a partner, who holds only the portal key, on to its portal and shows nothing', () => {
    const { queryAllByTestId } = renderAs(['portal.access']);

    expect(replace).toHaveBeenCalledWith({ name: 'portal-en' });
    expect(queryAllByTestId('workspace-card')).toEqual([]);
  });

  it('keeps the operator, who holds the portal key too, in the back office', () => {
    renderAs(['home.read', 'users.read', 'portal.access']);

    expect(replace).not.toHaveBeenCalled();
  });
});
