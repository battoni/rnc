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
// rnc-4 F5a: the firm manager holds its team's login keys (`users.*`) for /gestao/equipe.
const GESTAO = [...CEO, 'users.read', 'users.create', 'users.update', 'firm.settings'];

function keysFor(permissions: string[], roleSlug = 'operador') {
  return visibleWorkspaces((key) => permissions.includes(key), roleSlug).map((workspace) => workspace.key);
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

  // Chrome QA 5.2: `users.read` is the manager's too, so the operator's group also names the operator's role.
  it("never gives a firm manager the operator's group, whatever keys it holds", () => {
    expect(keysFor(GESTAO, 'gestao')).toEqual(['team', 'management']);
    expect(keysFor(OPERADOR, 'gestao')).toEqual(['team', 'management']);
  });

  it('gives an account without keys nothing', () => {
    expect(keysFor([])).toEqual([]);
  });
});
