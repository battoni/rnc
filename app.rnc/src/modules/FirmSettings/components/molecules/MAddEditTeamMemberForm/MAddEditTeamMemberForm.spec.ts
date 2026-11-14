import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import ToggleSwitch from 'primevue/toggleswitch';
import { beforeEach, describe, expect, it } from 'vitest';
import type { TeamRoleOption } from '../../../interfaces';
import type { User } from '@UserModule';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { MOneTimePassword } from '@PlatformModule';
import { useUserStore } from '@UserModule';
import { MANAGER_FIXTURE, TEAM_ROLES_FIXTURE } from '../../../test/fixtures';
import MAddEditTeamMemberForm from './MAddEditTeamMemberForm.vue';

/*
 * rnc-4 A39 (R31, W76): a team login made or edited by the firm's manager — names, username, a role
 * that is only ever `equipe` or `gestao`, and the specialist flag. A new login's password is made here
 * and shown once; the firm is the manager's own, never sent.
 */
const LIVIA: User = {
  id: 31,
  is_specialist: false,
  last_name: 'Fontana',
  name: 'Lívia',
  role: 'Equipe',
  role_slug: 'equipe',
  status: 'active',
  username: 'equipe.alvorada',
};

const sent: { body: Record<string, unknown>; method: string; url: string }[] = [];

function renderForm(user: User | null = null) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(MANAGER_FIXTURE as never, ['users.create', 'users.update']);

  return renderWithPlugins(MAddEditTeamMemberForm, {
    global: { components: { Button, InputText, MOneTimePassword, RadioButton, ToggleSwitch } },
    pinia,
    props: { roles: TEAM_ROLES_FIXTURE as TeamRoleOption[], user },
  });
}

function field(testId: string) {
  return screen.getByTestId(testId) as HTMLInputElement;
}

beforeEach(() => {
  sent.length = 0;

  server.use(
    http.post('http://localhost/auth/users', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      sent.push({ body, method: 'POST', url: request.url });

      return HttpResponse.json(
        { data: { ...body, id: 40, role: 'Equipe', role_slug: 'equipe', status: 'active' } },
        { status: 201 }
      );
    }),
    http.patch('http://localhost/auth/users/:id', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      sent.push({ body, method: 'PATCH', url: request.url });

      return HttpResponse.json({ data: { ...LIVIA, ...body, role: 'Gestão', role_slug: 'gestao' } });
    })
  );
});

describe('MAddEditTeamMemberForm', () => {
  it('offers only the two team roles', () => {
    renderForm();

    expect(screen.getAllByTestId('member-role-option').map((option) => option.textContent?.trim())).toEqual([
      'Equipe',
      'Gestão',
    ]);
  });

  it('creates a login with a generated password, and shows that password once', async () => {
    const { emitted } = renderForm();

    await fireEvent.update(field('member-name'), ' Otávio ');
    await fireEvent.update(field('member-last-name'), 'Reis');
    await fireEvent.update(field('member-username'), 'Otavio.Alvorada');
    await fireEvent.click(screen.getByLabelText('Equipe'));
    await fireEvent.click(screen.getByLabelText(/^Specialist/));
    await fireEvent.click(screen.getByTestId('member-save'));

    await waitFor(() => expect(sent).toHaveLength(1));

    const { body } = sent[0]!;

    expect(body).toMatchObject({
      is_specialist: true,
      last_name: 'Reis',
      name: 'Otávio',
      role_id: 3,
      status: 'active',
      username: 'otavio.alvorada',
    });
    expect(body.password).toMatch(/^[A-Za-z0-9]{16}$/);
    expect(body).not.toHaveProperty('firm_id');

    await waitFor(() => expect(screen.getByTestId('password-value').textContent?.trim()).toBe(body.password));
    expect(emitted().onSuccess?.[0]).toEqual([expect.objectContaining({ id: 40 })]);
  });

  it('asks for the names, the username and a role before sending', async () => {
    renderForm();

    await fireEvent.click(screen.getByTestId('member-save'));

    expect(sent).toHaveLength(0);
    expect(screen.getByTestId('member-name-error').textContent).toContain('Required');
    expect(screen.getByTestId('member-role-error').textContent).toContain('Required');
  });

  it('edits a login without touching its password', async () => {
    const { emitted } = renderForm(LIVIA);

    expect(field('member-username').value).toBe('equipe.alvorada');

    await fireEvent.click(screen.getByLabelText('Gestão'));
    await fireEvent.click(screen.getByTestId('member-save'));

    await waitFor(() => expect(sent).toHaveLength(1));

    expect(sent[0]!.method).toBe('PATCH');
    expect(sent[0]!.url).toMatch(/\/auth\/users\/31$/);
    expect(sent[0]!.body).toEqual({
      is_specialist: false,
      last_name: 'Fontana',
      name: 'Lívia',
      role_id: 2,
      username: 'equipe.alvorada',
    });
    await waitFor(() => expect(emitted().onSuccess?.[0]).toEqual([expect.objectContaining({ role_slug: 'gestao' })]));
  });

  it("shows the API's refusal of the role on the role", async () => {
    server.use(
      http.post('http://localhost/auth/users', () =>
        HttpResponse.json(
          { errors: { role_id: ['users.form.roleNotAssignable'] }, message: 'users.form.roleNotAssignable' },
          { status: 422 }
        )
      )
    );

    renderForm();

    await fireEvent.update(field('member-name'), 'Otávio');
    await fireEvent.update(field('member-last-name'), 'Reis');
    await fireEvent.update(field('member-username'), 'otavio.alvorada');
    await fireEvent.click(screen.getByLabelText('Gestão'));
    await fireEvent.click(screen.getByTestId('member-save'));

    await waitFor(() => expect(screen.getByTestId('member-role-error').textContent).toContain('team'));
    expect(screen.queryByTestId('password-value')).toBeNull();
  });
});
