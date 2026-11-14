import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { ALVORADA_FIXTURE } from '../../../test/fixtures';
import MOneTimePassword from '../MOneTimePassword/MOneTimePassword.vue';
import MFirmManagerForm from './MFirmManagerForm.vue';

/*
 * rnc-4 A29 (R25, W54): the firm manager's login from the console — the manager's username resets
 * its password, a free one creates the login; the password comes back once, with copy. Another
 * account's username is refused on the field.
 */
const sent: Record<string, unknown>[] = [];
const writeText = vi.fn(() => Promise.resolve());

function renderForm() {
  return renderWithPlugins(MFirmManagerForm, {
    props: { firm: structuredClone(ALVORADA_FIXTURE) },
    global: { components: { Button, InputText, MOneTimePassword } },
  });
}

function respondWith(created: boolean) {
  server.use(
    http.post('http://localhost/platform/firms/3/manager', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      sent.push(body);

      return HttpResponse.json(
        {
          data: {
            created,
            manager: { email: body.email, id: 41, username: body.username },
            password: 'A1b2C3d4E5f6G7h8',
          },
        },
        { status: created ? 201 : 200 }
      );
    })
  );
}

beforeEach(() => {
  sent.length = 0;
  writeText.mockClear();
  vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } });
});

afterEach(() => vi.unstubAllGlobals());

describe('MFirmManagerForm', () => {
  it("resets the manager's password and shows it once, with copy", async () => {
    respondWith(false);
    renderForm();

    await fireEvent.update(screen.getByTestId('manager-username'), ' Helena.Alvorada ');
    await fireEvent.click(screen.getByTestId('manager-save'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({ email: null, username: 'helena.alvorada' });
    expect((await screen.findByTestId('password-value')).textContent).toContain('A1b2C3d4E5f6G7h8');
    expect(screen.getByTestId('manager-result').textContent).toContain('reset');

    await fireEvent.click(screen.getByTestId('password-copy'));

    expect(writeText).toHaveBeenCalledWith('A1b2C3d4E5f6G7h8');
  });

  it('creates a free username as the new manager login', async () => {
    respondWith(true);
    renderForm();

    await fireEvent.update(screen.getByTestId('manager-username'), 'gestao.alvorada');
    await fireEvent.update(screen.getByTestId('manager-email'), 'gestao@alvorada.example');
    await fireEvent.click(screen.getByTestId('manager-save'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({ email: 'gestao@alvorada.example', username: 'gestao.alvorada' });
    expect((await screen.findByTestId('manager-result')).textContent).toContain('created');
  });

  it("refuses another account's username on the field", async () => {
    server.use(
      http.post('http://localhost/platform/firms/3/manager', () =>
        HttpResponse.json(
          { errors: { username: ['users.form.usernameTaken'] }, message: 'users.form.usernameTaken' },
          { status: 422 }
        )
      )
    );
    renderForm();

    await fireEvent.update(screen.getByTestId('manager-username'), 'reinaldo');
    await fireEvent.click(screen.getByTestId('manager-save'));

    await waitFor(() => expect(screen.getByTestId('manager-username-error').textContent).toContain('another account'));
    expect(screen.queryByTestId('password-value')).toBeNull();
  });

  it('asks for the username before sending', async () => {
    renderForm();

    await fireEvent.click(screen.getByTestId('manager-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('manager-username-error').textContent).toContain('Required');
  });
});
