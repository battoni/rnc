import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { AVAILABILITY_FIXTURE, BOOKING_FIXTURE, TOKEN_FIXTURE } from '../../../test/fixtures';
import MSlotPicker from './MSlotPicker.vue';

/*
 * The slot picker (rnc-1 F4, R15, R16, AM5): the specialists with the one indicated for the profile
 * chosen first, the working days with their free slots, the e-mail when the lead has none, and the
 * booking — with "esse horário acabou de ser reservado" when somebody took it first.
 */
const API = `http://localhost/public/diagnostics/${TOKEN_FIXTURE}`;
const bookings: unknown[] = [];
let availabilityCalls = 0;

function renderPicker(needsEmail = false) {
  return renderWithPlugins(MSlotPicker, {
    global: { components: { Button, InputText, Skeleton } },
    props: { needsEmail, token: TOKEN_FIXTURE },
  });
}

function slotButtons() {
  return screen.getAllByTestId('slot');
}

beforeEach(() => {
  bookings.length = 0;
  availabilityCalls = 0;
  server.use(
    http.get(`${API}/availability`, () => {
      availabilityCalls += 1;

      return HttpResponse.json({ data: AVAILABILITY_FIXTURE });
    }),
    http.post(`${API}/booking`, async ({ request }) => {
      bookings.push(await request.json());

      return HttpResponse.json({ data: BOOKING_FIXTURE }, { status: 201 });
    })
  );
});

describe('MSlotPicker', () => {
  it('starts on the specialist indicated for the profile, on the first day with a free slot', async () => {
    renderPicker();

    await screen.findAllByTestId('specialist');

    const specialists = screen.getAllByTestId('specialist');

    expect(specialists).toHaveLength(2);
    expect(specialists[1]!.getAttribute('aria-checked')).toBe('true');
    expect(specialists[1]!.textContent).toContain('Juliana Reis');
    expect(specialists[1]!.textContent).toContain('Recommended for your profile');
    expect(specialists[0]!.textContent).not.toContain('Recommended for your profile');

    const days = screen.getAllByTestId('slot-day');

    expect(days.map((day) => day.getAttribute('data-day'))).toEqual(['2026-11-23', '2026-11-24', '2026-11-25']);
    expect((days[0] as HTMLButtonElement).disabled).toBe(true);
    expect(days[1]!.getAttribute('aria-pressed')).toBe('true');
    expect(slotButtons().map((slot) => slot.textContent?.trim())).toEqual(['09:00', '14:30', '15:00']);
  });

  it('shows another specialist’s days and slots when chosen', async () => {
    renderPicker();
    await screen.findAllByTestId('specialist');

    await fireEvent.click(screen.getAllByTestId('specialist')[0]!);

    expect(screen.getAllByTestId('slot-day')[0]!.getAttribute('aria-pressed')).toBe('true');
    expect(slotButtons().map((slot) => slot.textContent?.trim())).toEqual(['08:00', '08:30']);
  });

  it('books the chosen slot and hands the confirmation up', async () => {
    const { emitted } = renderPicker();
    await screen.findAllByTestId('specialist');

    expect((screen.getByTestId('slot-confirm') as HTMLButtonElement).disabled).toBe(true);

    await fireEvent.click(slotButtons()[1]!);
    await fireEvent.click(screen.getByTestId('slot-confirm'));

    await waitFor(() => expect(emitted().onBooked).toBeTruthy());

    expect(bookings).toEqual([{ specialist_id: 3, starts_at: '2026-11-24T14:30:00-03:00' }]);
    expect(emitted().onBooked![0]).toEqual([BOOKING_FIXTURE]);
  });

  it('asks for the e-mail first when the lead left only a phone (AM5)', async () => {
    renderPicker(true);
    await screen.findAllByTestId('specialist');

    await fireEvent.click(slotButtons()[0]!);
    await fireEvent.click(screen.getByTestId('slot-confirm'));

    expect(screen.getByTestId('booking-error').textContent).toMatch(/e-mail/i);
    expect(bookings).toHaveLength(0);

    await fireEvent.update(screen.getByTestId('booking-email'), 'ana@horizonte.com.br');
    await fireEvent.click(screen.getByTestId('slot-confirm'));

    await waitFor(() => expect(bookings).toHaveLength(1));
    expect(bookings[0]).toEqual({
      email: 'ana@horizonte.com.br',
      specialist_id: 3,
      starts_at: '2026-11-24T09:00:00-03:00',
    });
  });

  it('says the slot was just taken and reloads the agenda when the API refuses it (409)', async () => {
    server.use(
      http.post(`${API}/booking`, () =>
        HttpResponse.json({ code: 'slot_taken', message: 'booking.slotTaken' }, { status: 409 })
      )
    );

    const { emitted } = renderPicker();
    await screen.findAllByTestId('specialist');

    await fireEvent.click(slotButtons()[0]!);
    await fireEvent.click(screen.getByTestId('slot-confirm'));

    expect((await screen.findByTestId('booking-error')).textContent).toMatch(/just been booked/i);
    await waitFor(() => expect(availabilityCalls).toBe(2));
    expect(emitted().onBooked).toBeFalsy();
  });
});
