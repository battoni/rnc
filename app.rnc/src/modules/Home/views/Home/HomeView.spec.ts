import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import HomeView from './HomeView.view.vue';

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
});
