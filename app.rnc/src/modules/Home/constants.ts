import type { Workspace } from './interfaces';

/**
 * The hub's three workspaces — the rail's groups (rnc-1 R3) and the home screen's cards.
 *
 * Each is closed by a key only its audience holds (`RoleSeeder`, plan AM8): `leads.read` is the
 * whole team's and `metrics.read` Reinaldo's. The rail filters by permission, so a key granted from the
 * permissions tab opens a group without a code change.
 *
 * The operator's is the exception: `users.read` is the firm manager's too since rnc-4 F5a (its team's
 * logins, at /gestao/equipe), so the group also names the operator's role — a manager saw it (Chrome QA 5.2).
 * Its landing route asks the same role (`meta.role`).
 */
const WORKSPACES: Workspace[] = [
  { icon: 'pi pi-filter', key: 'team', permission: 'leads.read', routeName: 'team-home' },
  { icon: 'pi pi-chart-bar', key: 'management', permission: 'metrics.read', routeName: 'management-home' },
  { icon: 'pi pi-cog', key: 'operator', permission: 'users.read', role: 'operador', routeName: 'operator-home' },
];

export { WORKSPACES };
