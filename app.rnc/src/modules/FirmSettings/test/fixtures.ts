import type { FirmSettings, Specialist, TeamRoleOption } from '../interfaces';
import type { User } from '@UserModule';

/* The API's answers FirmSettings specs need — the invented demo firm (Alvorada) only. */
const MANAGER_FIXTURE = {
  firm: {
    modules: ['portfolio', 'fiscal', 'radar', 'partners', 'contracts', 'briefing'],
    name: 'Alvorada Contabilidade',
    short_name: 'Alvorada',
    slug: 'alvorada',
  },
  id: 30,
  last_name: 'Alvorada',
  name: 'Helena',
  platform: { is_operator: false, name: 'Hub da Reforma', view_as: null },
  role: 'Gestão',
  role_slug: 'gestao',
  username: 'helena.alvorada',
};

/** `FirmSettingsResource`. */
const SETTINGS_FIXTURE: FirmSettings = {
  address: 'Rua das Acácias, 120 — Centro, Pouso Alegre/MG',
  business_hours: [{ from: '08:00', to: '18:00', weekdays: [1, 2, 3, 4, 5] }],
  credentials: null,
  mailbox: 'contato@alvorada.example',
  name: 'Alvorada Contabilidade',
  phone: '(35) 3421-0000',
  slug: 'alvorada',
};

/** `GET auth/roles` as the manager reads it: every role, the screen keeps the team's two. */
const ALL_ROLES_FIXTURE = [
  { id: 1, name: 'Operador', permissionGroups: [], slug: 'operador', users: [] },
  { id: 2, name: 'Gestão', permissionGroups: [], slug: 'gestao', users: [] },
  { id: 3, name: 'Equipe', permissionGroups: [], slug: 'equipe', users: [] },
  { id: 4, name: 'Parceiro', permissionGroups: [], slug: 'parceiro', users: [] },
];

/** What the team screen hands its form: the two team roles, Equipe first. */
const TEAM_ROLES_FIXTURE: TeamRoleOption[] = [
  { id: 3, name: 'Equipe', slug: 'equipe' },
  { id: 2, name: 'Gestão', slug: 'gestao' },
];

/** `GET auth/users` for the manager — its firm's logins only (AM2). */
const TEAM_FIXTURE: User[] = [
  { ...MANAGER_FIXTURE, is_specialist: false, status: 'active' } as User,
  {
    id: 31,
    is_specialist: false,
    last_name: 'Fontana',
    name: 'Lívia',
    role: 'Equipe',
    role_slug: 'equipe',
    status: 'active',
    username: 'equipe.alvorada',
  },
  {
    id: 32,
    is_specialist: true,
    last_name: 'Alvorada',
    name: 'Bruno',
    role: 'Equipe',
    role_slug: 'equipe',
    status: 'active',
    username: 'bruno.alvorada',
  },
];

/** `GET specialists` — `SpecialistResource`, hours in week order. */
const SPECIALISTS_FIXTURE: Specialist[] = [
  {
    area: 'Tributário',
    hours: [
      { closes_at: '12:00', opens_at: '09:00', weekday: 1 },
      { closes_at: '18:00', opens_at: '14:00', weekday: 1 },
      { closes_at: '17:00', opens_at: '09:00', weekday: 3 },
    ],
    id: 5,
    name: 'Bruno Alvorada',
    user_id: 32,
  },
  { area: null, hours: [], id: 6, name: 'Camila Alvorada', user_id: 33 },
];

export { ALL_ROLES_FIXTURE, MANAGER_FIXTURE, SETTINGS_FIXTURE, SPECIALISTS_FIXTURE, TEAM_FIXTURE, TEAM_ROLES_FIXTURE };
