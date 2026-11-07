import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { AGENDA_FIXTURE } from '../../test/fixtures';
import AgendaView from './AgendaView.view.vue';

/* The agenda week (rnc-1 F5, R20): Monday to Friday with the booked meetings; ◀ ▶ move a week. */
const weeksAsked: (string | null)[] = [];

const STUBS = {
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
};

function renderAgenda() {
  return renderWithPlugins(AgendaView, { global: { components: { Button, Skeleton }, stubs: STUBS } });
}

beforeEach(() => {
  weeksAsked.length = 0;
  server.use(
    http.get('http://localhost/agenda', ({ request }) => {
      const week = new URL(request.url).searchParams.get('week');

      weeksAsked.push(week);

      return HttpResponse.json({
        data:
          week === AGENDA_FIXTURE.next_week
            ? { ...AGENDA_FIXTURE, week_end: '2026-11-27', week_start: week }
            : AGENDA_FIXTURE,
      });
    })
  );
});

describe('AgendaView', () => {
  it('shows the five working days with the booked meetings', async () => {
    renderAgenda();

    const meeting = await screen.findByTestId('agenda-meeting');

    expect(screen.getAllByTestId('agenda-day')).toHaveLength(5);
    expect(meeting.textContent).toContain('Oficina Motor Certo');
    expect(meeting.textContent).toContain('09:00–09:30');
    expect(meeting.textContent).toContain('Mariana Toledo');
    expect(JSON.parse(meeting.dataset.to ?? '{}')).toMatchObject({ params: { id: 11 } });
    expect(screen.getByTestId('agenda-range').textContent).toContain('11/16');
    expect(screen.getByTestId('agenda-count').textContent).toContain('1 meeting');
  });

  it('moves to the next week', async () => {
    renderAgenda();

    await screen.findByTestId('agenda-meeting');
    await fireEvent.click(screen.getByTestId('agenda-next'));

    await waitFor(() => expect(weeksAsked).toEqual([null, '2026-11-23']));
    await waitFor(() => expect(screen.getByTestId('agenda-range').textContent).toContain('11/23'));
  });
});
