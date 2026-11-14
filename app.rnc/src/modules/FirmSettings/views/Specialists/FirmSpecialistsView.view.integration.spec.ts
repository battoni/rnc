import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MSpecialistHoursForm from '../../components/molecules/MSpecialistHoursForm/MSpecialistHoursForm.vue';
import { MANAGER_FIXTURE, SPECIALISTS_FIXTURE } from '../../test/fixtures';
import FirmSpecialistsView from './FirmSpecialistsView.view.vue';

/*
 * rnc-4 A39 (R32, C17): each bookable specialist's weekly hours — the windows the agenda offers to a
 * visitor. Editing one sends its whole week (`PUT specialists/{id}/hours`) and the answer replaces its
 * row. A person whose specialist flag is off is not here (C17): the flag is the team screen's.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  RouterLink: { props: ['to'], template: '<a><slot /></a>' },
  MMainDialog: {
    props: ['title', 'visible'],
    template: '<div v-if="visible" data-testid="dialog" :data-title="title"><slot /></div>',
  },
};

const sent: { body: { hours: unknown[] }; url: string }[] = [];

function renderSpecialists() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(MANAGER_FIXTURE as never, ['firm.settings']);

  return renderWithPlugins(FirmSpecialistsView, {
    global: { components: { Button, InputText, MSpecialistHoursForm, Skeleton }, stubs: STUBS },
    pinia,
  });
}

function rowOf(name: string) {
  return screen.getAllByTestId('specialist-row').find((row) => row.textContent?.includes(name))!;
}

beforeEach(() => {
  sent.length = 0;

  server.use(
    http.get('http://localhost/specialists', () => HttpResponse.json({ data: SPECIALISTS_FIXTURE })),
    http.put('http://localhost/specialists/:id/hours', async ({ request }) => {
      const body = (await request.json()) as { hours: unknown[] };

      sent.push({ body, url: request.url });

      return HttpResponse.json({ data: body.hours });
    })
  );
});

describe('FirmSpecialistsView', () => {
  it('lists each specialist with the week in reading order', async () => {
    renderSpecialists();

    await waitFor(() => expect(screen.getAllByTestId('specialist-row')).toHaveLength(2));

    const bruno = rowOf('Bruno Alvorada');

    expect(bruno.textContent).toContain('Tributário');
    expect(
      within(bruno)
        .getAllByTestId('specialist-hour')
        .map((hour) => hour.textContent?.trim())
    ).toEqual(['Mon 09:00–12:00', 'Mon 14:00–18:00', 'Wed 09:00–17:00']);
    expect(within(rowOf('Camila Alvorada')).getByTestId('specialist-no-hours')).toBeTruthy();
  });

  it('sends the whole week of one specialist and puts the answer in its row', async () => {
    renderSpecialists();

    await waitFor(() => expect(screen.getAllByTestId('specialist-row')).toHaveLength(2));

    await fireEvent.click(within(rowOf('Camila Alvorada')).getByTestId('specialist-edit'));
    await fireEvent.click(screen.getByTestId('hour-add'));
    await fireEvent.update(screen.getByTestId('hour-weekday-0'), '2');
    await fireEvent.update(screen.getByTestId('hour-opens-0'), '10:00');
    await fireEvent.update(screen.getByTestId('hour-closes-0'), '16:00');
    await fireEvent.click(screen.getByTestId('hours-save'));

    await waitFor(() => expect(sent).toHaveLength(1));

    expect(sent[0]!.url).toMatch(/\/specialists\/6\/hours$/);
    expect(sent[0]!.body).toEqual({ hours: [{ closes_at: '16:00', opens_at: '10:00', weekday: 2 }] });
    await waitFor(() =>
      expect(within(rowOf('Camila Alvorada')).getAllByTestId('specialist-hour')[0]!.textContent?.trim()).toBe(
        'Tue 10:00–16:00'
      )
    );
  });

  it('refuses a window that closes before it opens, before sending', async () => {
    renderSpecialists();

    await waitFor(() => expect(screen.getAllByTestId('specialist-row')).toHaveLength(2));

    await fireEvent.click(within(rowOf('Bruno Alvorada')).getByTestId('specialist-edit'));
    await fireEvent.update(screen.getByTestId('hour-closes-0'), '08:00');
    await fireEvent.click(screen.getByTestId('hours-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('hour-error-0').textContent).toMatch(/after/i);
  });

  it("shows the API's refusal in the form", async () => {
    server.use(
      http.put('http://localhost/specialists/:id/hours', () =>
        HttpResponse.json(
          { errors: { 'hours.0.closes_at': ['validation.after'] }, message: 'validation.after' },
          { status: 422 }
        )
      )
    );

    renderSpecialists();

    await waitFor(() => expect(screen.getAllByTestId('specialist-row')).toHaveLength(2));

    await fireEvent.click(within(rowOf('Bruno Alvorada')).getByTestId('specialist-edit'));
    await fireEvent.click(screen.getByTestId('hours-save'));

    await waitFor(() => expect(screen.getByTestId('hour-error-0').textContent).not.toBe(''));
  });

  it('says who to flag when the firm has no specialist yet', async () => {
    server.use(http.get('http://localhost/specialists', () => HttpResponse.json({ data: [] })));

    renderSpecialists();

    expect((await screen.findByTestId('specialists-empty')).textContent).toContain('Team');
  });

  it('says the specialists did not load and offers try again', async () => {
    server.use(http.get('http://localhost/specialists', () => HttpResponse.json({ error: 'boom' }, { status: 500 })));
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderSpecialists();

    expect((await screen.findByRole('alert')).textContent).toContain('could not be loaded');
  });
});
