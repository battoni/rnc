import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { NavItem } from './interfaces';
import { ASSISTANT_PERMISSIONS, ASSISTANT_ROUTE_NAMES } from '@AssistantModule';
import { BRANDS_PERMISSIONS, BRANDS_ROUTE_NAMES } from '@BrandsModule';
import { CHANNEL_PERMISSIONS, CHANNEL_ROUTE_NAMES } from '@ChannelModule';
import { WORKSPACES } from '@HomeModule';
import { METRICS_PERMISSIONS, METRICS_ROUTE_NAMES } from '@MetricsModule';
import { PARTNER_PERMISSIONS, PARTNER_ROUTE_NAMES } from '@PartnerModule';
import { PIPELINE_PERMISSIONS, PIPELINE_ROUTE_NAMES } from '@PipelineModule';
import { PORTAL_PERMISSIONS, PORTAL_ROLE_SLUG, PORTAL_ROUTE_NAMES } from '@PortalModule';
import { REPRICING_PERMISSIONS, REPRICING_ROUTE_NAMES } from '@RepricingModule';
import { useUserStore } from '@UserModule';

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
/**
 * What each group already holds. The team's has its pipeline and agenda (F5); the operator's carries
 * the bootstrap's own administration screens; Reinaldo's has the metrics (F7), the network (F6), its ledger (rnc-2 F4) and the fee repricing (F8); rnc-3 F3b puts the RNC channel in his and the operator's. Route
 * names are the owners' (celer-05), never an import of their screens.
 */
const WORKSPACE_CHILDREN: Record<string, NavItem[] | undefined> = {
  team: [
    {
      icon: 'pi pi-th-large',
      label: 'navigation.pipeline',
      permission: PIPELINE_PERMISSIONS.read,
      routeName: PIPELINE_ROUTE_NAMES.board,
    },
    {
      icon: 'pi pi-calendar',
      label: 'navigation.agenda',
      permission: PIPELINE_PERMISSIONS.agenda,
      routeName: PIPELINE_ROUTE_NAMES.agenda,
    },
    // rnc-3 F5: the Reforma assistant, for the team and Reinaldo (R12).
    {
      icon: 'pi pi-comments',
      label: 'navigation.assistant',
      permission: ASSISTANT_PERMISSIONS.ask,
      routeName: ASSISTANT_ROUTE_NAMES.ask,
    },
  ],
  ceo: [
    {
      icon: 'pi pi-chart-line',
      label: 'navigation.metrics',
      permission: METRICS_PERMISSIONS.read,
      routeName: METRICS_ROUTE_NAMES.funnel,
    },
    {
      icon: 'pi pi-sitemap',
      label: 'navigation.partners',
      permission: PARTNER_PERMISSIONS.manage,
      routeName: PARTNER_ROUTE_NAMES.partners,
    },
    {
      icon: 'pi pi-shield',
      label: 'navigation.trail',
      permission: PARTNER_PERMISSIONS.trail,
      routeName: PARTNER_ROUTE_NAMES.trail,
    },
    {
      icon: 'pi pi-wallet',
      label: 'navigation.ledger',
      permission: PARTNER_PERMISSIONS.ledger,
      routeName: PARTNER_ROUTE_NAMES.ledger,
    },
    {
      icon: 'pi pi-percentage',
      label: 'navigation.repricing',
      permission: REPRICING_PERMISSIONS.manage,
      routeName: REPRICING_ROUTE_NAMES.list,
    },
    // rnc-3 F3b: the RNC channel, read-only for Reinaldo (R9).
    {
      icon: 'pi pi-share-alt',
      label: 'navigation.channel',
      permission: CHANNEL_PERMISSIONS.read,
      routeName: CHANNEL_ROUTE_NAMES.ceo,
    },
  ],
  operator: [
    { icon: 'pi pi-users', label: 'navigation.users', permission: 'users.read', routeName: 'users' },
    {
      icon: 'pi pi-key',
      label: 'navigation.roles',
      permission: 'permissions.read',
      routeName: 'roles-and-permissions',
    },
    // rnc-3 F2: Franco's sub-brands (R5), the operator's alone (D3).
    {
      icon: 'pi pi-palette',
      label: 'navigation.brands',
      permission: BRANDS_PERMISSIONS.manage,
      routeName: BRANDS_ROUTE_NAMES.list,
    },
    // rnc-3 F3b: the RNC channel Franco runs (R8).
    {
      icon: 'pi pi-share-alt',
      label: 'navigation.channel',
      permission: CHANNEL_PERMISSIONS.read,
      routeName: CHANNEL_ROUTE_NAMES.operator,
    },
  ],
};

/**
 * "Base da Reforma" (rnc-3 R10, AM4): under Equipe, for the people who write it — a specialist or
 * `assistant.manage`. "Specialist" is a flag on the person, not a key, so this entry is added from
 * the signed-in user rather than gated by `permission` alone.
 */
const KNOWLEDGE_ENTRY: NavItem = {
  icon: 'pi pi-book',
  label: 'navigation.knowledge',
  permission: ASSISTANT_PERMISSIONS.ask,
  routeName: ASSISTANT_ROUTE_NAMES.knowledge,
};

/**
 * The partner's portal (rnc-2 R3, A4): a partner's whole rail. Gated on the key AND the role — the
 * operator holds every key, `portal.access` included, and must never see a portal link; a partner
 * holds none of the three groups' keys, so it sees only this (and the core Home, which sends it here).
 * Its one child is the statement (rnc-2 F4, R11), behind the same key and role.
 */
const PORTAL_ENTRY: NavItem = {
  children: [
    {
      icon: 'pi pi-wallet',
      label: 'navigation.statement',
      permission: PORTAL_PERMISSIONS.access,
      role: PORTAL_ROLE_SLUG,
      routeName: PORTAL_ROUTE_NAMES.statement,
    },
  ],
  icon: 'pi pi-briefcase',
  label: 'navigation.portal',
  permission: PORTAL_PERMISSIONS.access,
  role: PORTAL_ROLE_SLUG,
  routeName: PORTAL_ROUTE_NAMES.home,
};

/**
 * The hub's rail (rnc-1 R3): Equipe · Reinaldo · Operador, in that order, each closed by the key
 * only its audience holds (`WORKSPACES`, plan AM8) — so the team sees one group, Reinaldo two and
 * the operator all three. Each is a link with a toggle (shape 3) once it has children.
 */
function useLocalNavItems(): ComputedRef<NavItem[]> {
  const userStore = useUserStore();

  return computed<NavItem[]>(() => [
    ...WORKSPACES.map((workspace) => ({
      children: childrenOf(workspace.key, writesKnowledge(userStore)),
      icon: workspace.icon,
      label: `navigation.${workspace.key}`,
      permission: workspace.permission,
      routeName: workspace.routeName,
    })),
    PORTAL_ENTRY,
  ]);
}

function writesKnowledge(userStore: ReturnType<typeof useUserStore>): boolean {
  return Boolean(userStore.user?.is_specialist) || userStore.hasPermission(ASSISTANT_PERMISSIONS.manage);
}

function childrenOf(key: string, withKnowledge: boolean): NavItem[] | undefined {
  const children = WORKSPACE_CHILDREN[key];

  return key === 'team' && children && withKnowledge ? [...children, KNOWLEDGE_ENTRY] : children;
}

const localNavSettings: NavItem[] = [];

export { localNavSettings, useLocalNavItems };
