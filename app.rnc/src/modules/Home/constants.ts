import type { Workspace } from './interfaces';

/**
 * The hub's three workspaces — the rail's groups (rnc-1 R3) and the home screen's cards.
 *
 * Each is closed by a key only its audience holds (`RoleSeeder`, plan AM8): `leads.read` is the
 * whole team's, `metrics.read` Reinaldo's and `users.read` the operator's. The rail filters by
 * permission, never by role slug, so a key granted from the permissions tab opens a group without
 * a code change.
 */
const WORKSPACES: Workspace[] = [
  { icon: 'pi pi-filter', key: 'team', permission: 'leads.read', routeName: 'team-home' },
  { icon: 'pi pi-chart-bar', key: 'ceo', permission: 'metrics.read', routeName: 'ceo-home' },
  { icon: 'pi pi-cog', key: 'operator', permission: 'users.read', routeName: 'operator-home' },
];

export { WORKSPACES };
