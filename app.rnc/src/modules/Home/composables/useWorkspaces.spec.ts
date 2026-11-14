import { describe, expect, it } from 'vitest';
import { visibleWorkspaces } from './useWorkspaces';

/*
 * A2 (rnc-1), SPA side: each role sees only its rail groups. The keys are the seeded ones
 * (`RoleSeeder`, plan AM8) — the rail filters by permission, so this is the role table read
 * through the same gate.
 */
const EQUIPE = ['home.read', 'leads.read', 'leads.update', 'agenda.read', 'notifications.read'];
const CEO = [...EQUIPE, 'partners.manage', 'referrals.read', 'metrics.read', 'repricing.manage', 'clients.import'];
const OPERADOR = [...CEO, 'users.read', 'users.create', 'permissions.read', 'permissions.update'];

function keysFor(permissions: string[]) {
  return visibleWorkspaces((key) => permissions.includes(key)).map((workspace) => workspace.key);
}

describe('visibleWorkspaces', () => {
  it('gives the team only the Equipe group', () => {
    expect(keysFor(EQUIPE)).toEqual(['team']);
  });

  it('gives Reinaldo the Equipe and Reinaldo groups', () => {
    expect(keysFor(CEO)).toEqual(['team', 'management']);
  });

  it('gives the operator all three groups', () => {
    expect(keysFor(OPERADOR)).toEqual(['team', 'management', 'operator']);
  });

  it('gives an account without keys nothing', () => {
    expect(keysFor([])).toEqual([]);
  });
});
