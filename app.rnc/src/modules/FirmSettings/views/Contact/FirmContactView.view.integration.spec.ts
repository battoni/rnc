import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MBusinessHoursEditor from '../../components/molecules/MBusinessHoursEditor/MBusinessHoursEditor.vue';
import { MANAGER_FIXTURE, SETTINGS_FIXTURE } from '../../test/fixtures';
import FirmContactView from './FirmContactView.view.vue';

/*
 * rnc-4 A39 (R30, C16): the firm's contact data — address, phone, mailbox, the footer's credentials
 * line and the business hours, as `PATCH firm/settings` takes them. The look and the modules are the
 * operator's and are not on this screen.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const sent: Record<string, unknown>[] = [];

function renderContact() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(MANAGER_FIXTURE as never, ['firm.settings']);

  return renderWithPlugins(FirmContactView, {
    global: { components: { Button, Checkbox, InputText, MBusinessHoursEditor, Skeleton } },
    pinia,
  });
}

function field(testId: string) {
  return screen.getByTestId(testId) as HTMLInputElement;
}

beforeEach(() => {
  sent.length = 0;

  server.use(
    http.get('http://localhost/firm/settings', () => HttpResponse.json({ data: SETTINGS_FIXTURE })),
    http.patch('http://localhost/firm/settings', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      sent.push(body);

      return HttpResponse.json({ data: { ...SETTINGS_FIXTURE, ...body } });
    })
  );
});

describe('FirmContactView', () => {
  it('opens with the firm’s contact data and hours, and offers no look or module field', async () => {
    renderContact();

    await waitFor(() => expect(field('contact-address').value).toBe(SETTINGS_FIXTURE.address));

    expect(field('contact-phone').value).toBe(SETTINGS_FIXTURE.phone);
    expect(field('contact-mailbox').value).toBe(SETTINGS_FIXTURE.mailbox);
    expect(field('contact-credentials').value).toBe('');
    expect(screen.getAllByTestId('hours-window')).toHaveLength(1);
    expect(screen.queryByText(/colour/i)).toBeNull();
    expect(screen.queryByText(/module/i)).toBeNull();
  });

  it('sends the contact data, the credentials line and the hours in one PATCH', async () => {
    renderContact();

    await waitFor(() => expect(field('contact-address').value).toBe(SETTINGS_FIXTURE.address));

    await fireEvent.update(field('contact-phone'), '(35) 3421-9999');
    await fireEvent.update(field('contact-credentials'), 'CRC-MG 012345/O');
    await fireEvent.click(screen.getByTestId('hours-add'));

    const windows = screen.getAllByTestId('hours-window');

    expect(windows).toHaveLength(2);

    await fireEvent.click(screen.getByTestId('hours-weekday-1-6'));
    await fireEvent.update(screen.getByTestId('hours-from-1'), '08:00');
    await fireEvent.update(screen.getByTestId('hours-to-1'), '12:00');
    await fireEvent.click(screen.getByTestId('contact-save'));

    await waitFor(() =>
      expect(sent).toEqual([
        {
          address: SETTINGS_FIXTURE.address,
          business_hours: [
            { from: '08:00', to: '18:00', weekdays: [1, 2, 3, 4, 5] },
            { from: '08:00', to: '12:00', weekdays: [6] },
          ],
          credentials: 'CRC-MG 012345/O',
          mailbox: SETTINGS_FIXTURE.mailbox,
          phone: '(35) 3421-9999',
        },
      ])
    );
  });

  it('refuses a window with no weekday or closing before it opens, before sending', async () => {
    renderContact();

    await waitFor(() => expect(field('contact-address').value).toBe(SETTINGS_FIXTURE.address));

    await fireEvent.click(screen.getByTestId('hours-add'));
    await fireEvent.update(screen.getByTestId('hours-from-1'), '14:00');
    await fireEvent.update(screen.getByTestId('hours-to-1'), '13:00');
    await fireEvent.click(screen.getByTestId('contact-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('hours-error-1').textContent).toMatch(/weekday/i);
  });

  it("shows the API's refusal on the field it names", async () => {
    server.use(
      http.patch('http://localhost/firm/settings', () =>
        HttpResponse.json({ errors: { mailbox: ['validation.email'] }, message: 'validation.email' }, { status: 422 })
      )
    );

    renderContact();

    await waitFor(() => expect(field('contact-address').value).toBe(SETTINGS_FIXTURE.address));

    await fireEvent.click(screen.getByTestId('contact-save'));

    await waitFor(() => expect(screen.getByTestId('contact-mailbox-error').textContent).not.toBe(''));
  });

  it('says the settings did not load and offers try again', async () => {
    server.use(http.get('http://localhost/firm/settings', () => HttpResponse.json({ error: 'boom' }, { status: 500 })));
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderContact();

    expect((await screen.findByRole('alert')).textContent).toContain('could not be loaded');
  });
});
