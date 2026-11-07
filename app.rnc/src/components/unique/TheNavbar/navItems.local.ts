import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { NavItem } from './interfaces';

/**
 * Project-owned navigation items — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * Add your feature-module nav entries here; they are appended after the core items in
 * TheNavbar.vue and survive every rnc update. `useLocalNavItems()` feeds the main nav,
 * `localNavSettings` the secondary (settings) group. Labels are i18n keys unless the entry says
 * `isLabelLiteral` — for a row named by the person rather than by the product.
 *
 * `useLocalNavItems` is a composable, not a constant, and TheNavbar calls it once in setup. That
 * is what lets a group's children come from a store instead of from a list written here: keep it
 * cheap and side-effect-light, because it runs on every navigation.
 *
 * An entry's shape decides its behavior — three shapes exist, and nothing carries a type flag,
 * so the data and the behavior cannot drift apart:
 *
 * 1. Plain link — `routeName` only; clicking navigates:
 *    { icon: 'pi pi-building', label: 'navigation.clients', permission: 'clients.read', routeName: 'clients' }
 *
 * 2. Expander — `children` and no `routeName`; clicking the row expands and collapses it:
 *    {
 *      icon: 'pi pi-folder',
 *      label: 'navigation.reports',
 *      children: [
 *        { icon: 'pi pi-chart-bar', label: 'navigation.sales', permission: 'sales.read', routeName: 'sales' },
 *      ],
 *    }
 *
 * 3. Link with toggle — `children` AND a `routeName`; clicking the text navigates while the
 *    chevron expands and collapses without navigating.
 *
 * Children are one level deep, always plain links, and go through the same permission/role
 * filtering and i18n route resolution as their parents. A parent with no `routeName` and no
 * visible children never renders — a group emptied by permissions leads nowhere, so it stops
 * being an empty expander. A parent reads as active while one of its children's routes is on
 * screen. Expanded state persists per group key (`key` ?? `routeName` ?? `label`), one group at
 * a time. The mobile bottom bar stays flat: children never appear there.
 *
 * A group whose children come from a store asks for the data here, not inside the module — the
 * navigation is on every screen, so waiting for the module to load would leave the group empty
 * on arrival. Do not await: the group renders with what it has and grows when the answer lands.
 */
function useLocalNavItems(): ComputedRef<NavItem[]> {
  return computed<NavItem[]>(() => []);
}

const localNavSettings: NavItem[] = [];

export { localNavSettings, useLocalNavItems };
