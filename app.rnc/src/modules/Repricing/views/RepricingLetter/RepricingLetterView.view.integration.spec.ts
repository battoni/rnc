import { fireEvent, screen } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { LETTER_FIXTURE } from '../../test/fixtures';
import RepricingLetterView from './RepricingLetterView.view.vue';

/*
 * The letter of repricing (rnc-1 F8, R26): a printable page naming the client, today's and the
 * suggested fee, the items behind it, signed by the firm with its contact.
 */
let routeId = '1';

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: { id: routeId }, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

function renderLetter() {
  return renderWithPlugins(RepricingLetterView, {
    global: {
      components: { Button, Skeleton },
      stubs: {
        ALogo: { template: '<span data-testid="logo" />' },
        RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
      },
    },
  });
}

beforeEach(() => {
  routeId = '1';
  server.use(
    http.get('http://localhost/repricing/1/letter', () => HttpResponse.json({ data: LETTER_FIXTURE })),
    http.get('http://localhost/repricing/9/letter', () =>
      HttpResponse.json({ message: 'portfolio.notFound' }, { status: 404 })
    )
  );
});

describe('RepricingLetterView', () => {
  it('names the client, both fees and every item, signed by the firm', async () => {
    renderLetter();

    const letter = await screen.findByTestId('repricing-letter');

    expect(letter.getAttribute('data-client')).toBe('1');
    expect(letter.textContent).toContain('Consultoria Aurora Ltda');
    expect(letter.textContent).toContain('99.900.001/9999-01');
    expect(screen.getByTestId('letter-current').textContent).toContain('2.000,00');
    expect(screen.getByTestId('letter-suggested').textContent).toContain('2.200,00');
    expect(screen.getAllByTestId('letter-item').map((item) => item.textContent?.trim())).toEqual([
      'Crédito do Simples',
      'Split payment no caixa',
    ]);
    expect(screen.getByTestId('letter-signature').textContent).toContain('Franco Contabilidade');
    expect(letter.textContent).toContain('contato@franco.exemplo');
  });

  it('prints the page', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => undefined);

    renderLetter();

    await fireEvent.click(await screen.findByTestId('letter-print'));

    expect(print).toHaveBeenCalledOnce();
  });

  it('says a client outside the portfolio does not exist', async () => {
    routeId = '9';

    renderLetter();

    expect((await screen.findByRole('alert')).textContent).toContain('not in the portfolio');
  });
});
