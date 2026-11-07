import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseNavItems } from './extract-views.mjs';

test('parseNavItems: an item restricted by role is staff, whatever the role is spelled as', () => {
  const src = `
const NAV_ITEMS: NavItem[] = [
  { icon: 'pi pi-home', label: 'navigation.home', routeName: 'home' },
  { icon: 'pi pi-key', label: 'navigation.roles', permission: 'permissions.read', routeName: 'roles-and-permissions' },
  { icon: 'pi pi-cog', label: 'navigation.settings', role: 'administrator', routeName: 'settings' },
  { icon: 'pi pi-chart-bar', label: 'navigation.reports', role: ROLES.ADMIN, routeName: 'reports' },
  // { label: 'navigation.ghost', role: 'administrator', routeName: 'ghost' },
];
`;
  assert.deepEqual(
    parseNavItems(src).map((item) => `${item.target}:${item.permission ?? '-'}:${item.staff ? 'staff' : 'open'}`),
    ['home:-:open', 'roles-and-permissions:permissions.read:open', 'settings:-:staff', 'reports:-:staff'],
  );
});

test('parseNavItems: a key that only ends in "role" does not make an item staff', () => {
  const src = `{ icon: 'pi pi-user', label: 'navigation.users', navRole: 'x', routeName: 'users' }`;
  assert.equal(parseNavItems(src)[0].staff, false);
});
