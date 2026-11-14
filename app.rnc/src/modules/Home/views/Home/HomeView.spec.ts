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

function renderAs(permissions: string[], session: object = {}) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      id: 1,
      name: 'Reinaldo',
      last_name: 'Franco',
      username: 'reinaldo',
      role: 'CEO',
      role_slug: 'gestao',
      ...session,
    } as never,
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

    expect(queryAllByTestId('workspace-card').map((card) => card.dataset.workspace)).toEqual(['team', 'management']);
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

  // rnc-4 R24: the firm-less operator's back office is the platform console.
  it('sends the firm-less operator on to the platform console and shows nothing', () => {
    const { queryAllByTestId } = renderAs(['home.read', 'users.read', 'platform.firms'], {
      firm: null,
      platform: { is_operator: true, name: 'Hub da Reforma', view_as: null },
    });

    expect(replace).toHaveBeenCalledWith({ name: 'platform-firms-en' });
    expect(queryAllByTestId('workspace-card')).toEqual([]);
  });

  // rnc-4 R26: inside a view-as the operator's back office is the viewed firm's.
  it('keeps the operator in the back office while a view-as is on', () => {
    renderAs(['home.read', 'users.read', 'platform.firms'], {
      firm: null,
      platform: {
        is_operator: true,
        name: 'Hub da Reforma',
        view_as: { name: 'Alvorada Contabilidade', slug: 'alvorada' },
      },
    });

    expect(replace).not.toHaveBeenCalled();
  });

  it('keeps a person with a firm in the back office, whatever the platform says', () => {
    renderAs(['home.read', 'metrics.read'], {
      firm: { modules: [], name: 'Alvorada Contabilidade', slug: 'alvorada' },
      platform: { is_operator: false, name: 'Hub da Reforma', view_as: null },
    });

    expect(replace).not.toHaveBeenCalled();
  });
});
