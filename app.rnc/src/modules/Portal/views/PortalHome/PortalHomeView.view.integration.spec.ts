import { fireEvent, screen } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import PortalHomeView from './PortalHomeView.view.vue';

/*
 * The partner's landing (rnc-2 F1, R3): it names the partner the API says is signed in, and the
 * firm whose portal it is; a failed load offers a retry.
 */
const ME = {
  firm: { name: 'Franco Contabilidade', slug: 'franco' },
  partner: { id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
};

let answer: () => Response;

function renderView() {
  return renderWithPlugins(PortalHomeView, {
    global: { components: { Button, Skeleton } },
  });
}

beforeEach(() => {
  answer = () => HttpResponse.json({ data: ME });
  server.use(http.get('http://localhost/portal/me', () => answer()));
});

describe('PortalHomeView', () => {
  it("names the signed-in partner and Franco's firm", async () => {
    renderView();

    expect((await screen.findByTestId('portal-partner')).textContent).toContain('NexoERP (exemplo)');
    expect(screen.getByTestId('portal-home').textContent).toContain('Franco Contabilidade');
  });

  it('offers a retry when the portal does not answer', async () => {
    answer = () => HttpResponse.json({ error: 'x' }, { status: 500 });
    renderView();

    const retry = await screen.findByRole('button', { name: /try again/i });

    answer = () => HttpResponse.json({ data: ME });
    await fireEvent.click(retry);

    expect((await screen.findByTestId('portal-partner')).textContent).toContain('NexoERP (exemplo)');
  });
});
