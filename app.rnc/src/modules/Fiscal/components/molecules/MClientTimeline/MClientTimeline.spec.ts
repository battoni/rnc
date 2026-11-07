import { screen } from '@testing-library/vue';
import Skeleton from 'primevue/skeleton';
import { describe, expect, it } from 'vitest';
import type { ClientEvent } from '../../../interfaces';
import { renderWithPlugins } from '@/test/render';
import { NOTICE_EVENT, REVIEW_EVENT } from '../../../test/fixtures';
import MClientTimeline from './MClientTimeline.vue';

/*
 * A client's timeline (rnc-2 F8, R20, R22), shared by the two fiscal screens: one sentence per line
 * from its type and the figures it was written on, who wrote it and when (São Paulo). The API keeps
 * the facts; the SPA writes the sentence (`ClientEventResource`).
 */
function renderTimeline(events: ClientEvent[], extra: Record<string, unknown> = {}) {
  return renderWithPlugins(MClientTimeline, {
    props: { events, failed: false, loading: false, ...extra },
    global: { components: { Skeleton } },
  });
}

describe('MClientTimeline', () => {
  it('writes a notice and a review with their figures, who and when', () => {
    renderTimeline([NOTICE_EVENT, REVIEW_EVENT]);

    const [notice, review] = screen.getAllByTestId('timeline-line');

    expect(notice!.getAttribute('data-type')).toBe('nfe.aviso');
    expect(notice!.textContent).toContain('40');
    expect(notice!.textContent).toContain('Patrícia Lima');
    expect(notice!.textContent).toContain('09/11/2026');
    expect(review!.getAttribute('data-type')).toBe('fornecedores.revisao');
    expect(review!.textContent).toMatch(/77\.400,00/);
    expect(review!.textContent).toMatch(/84\.600,00/);
    expect(review!.textContent).toContain('Reinaldo Franco');
  });

  it('names a line no person wrote, and a type it does not know', () => {
    renderTimeline([{ ...NOTICE_EVENT, data: {}, id: 60, type: 'outro.tipo', user: null }]);

    const [line] = screen.getAllByTestId('timeline-line');

    expect(line!.getAttribute('data-type')).toBe('outro.tipo');
    expect(line!.textContent).toContain('outro.tipo');
  });

  it('says the client has no line yet', () => {
    renderTimeline([]);

    expect(screen.getByTestId('timeline-empty')).toBeTruthy();
  });

  it('says the timeline did not load', () => {
    renderTimeline([], { failed: true });

    expect(screen.getByRole('alert')).toBeTruthy();
  });
});
