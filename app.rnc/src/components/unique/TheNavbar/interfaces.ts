import type { RouteLocationRaw } from 'vue-router';

/**
 * Declared navigation entry. The shape decides the behavior — nothing is configured twice:
 *
 * - `routeName` only → plain link: clicking navigates.
 * - `children` and no `routeName` → expander: clicking the row expands/collapses its children.
 * - `children` and a `routeName` → link with toggle: the text navigates, the chevron expands/collapses.
 *
 * Children are one level deep and always plain links — a child without a `routeName` is dropped
 * at resolution. `permission`/`role` gate parents and children alike, and a parent with no route
 * of its own and no visible children is dropped entirely.
 */
interface NavItem {
  children?: NavItem[];
  icon: string;
  /**
   * True when `label` is the text itself rather than an i18n key. The exception exists for
   * children built from a person's own records — a trail's name is not translatable, and
   * running it through `$t()` would make a name that happens to match a key render as that
   * key's translation.
   */
  isLabelLiteral?: boolean;
  /** Overrides the derived key. Required when several entries resolve the same route name. */
  key?: string;
  label: string;
  /** Route params, for an entry pointing at a parameterised route (`/trilhas/:id`). */
  params?: Record<string, string>;
  permission?: string;
  role?: string;
  routeName?: string;
}

/** A child after filtering and route resolution — always a plain link. */
interface NavChildRouteItem {
  /** An attention dot: something here has not been seen. Sourced from `navBadges.local.ts`. */
  hasDot?: boolean;
  icon: string;
  isLabelLiteral?: boolean;
  /** The v-for key. The i18n label is not unique — localNavItems is a documented extension
   *  point, and a project that reuses a label would silently break reconciliation. */
  key: string;
  label: string;
  route: RouteLocationRaw;
  routeName: string;
}

/** A top-level entry after filtering and route resolution. `route` is absent on pure expanders. */
interface NavRouteItem {
  /** An attention dot: something here has not been seen. Sourced from `navBadges.local.ts`. */
  hasDot?: boolean;
  children?: NavChildRouteItem[];
  icon: string;
  isLabelLiteral?: boolean;
  /** `routeName` when the item declares one, its label otherwise — see NavChildRouteItem.key. */
  key: string;
  label: string;
  route?: RouteLocationRaw;
  routeName?: string;
}

/** An entry guaranteed to navigate — what the settings block and personalization render. */
type NavLinkItem = NavRouteItem & { route: RouteLocationRaw };

/**
 * An address OUTSIDE the app — the documentation portal today.
 *
 * Deliberately not a `NavItem` with an `href`: everything in that pipeline resolves a route name,
 * gates on a permission and renders a `RouterLink`, and an entry that does none of those would be
 * three `v-if`s deep in every variant. This is a different thing that happens to sit in the same
 * list, so it has its own small shape and its own branch.
 */
interface NavExternalItem {
  href: string;
  icon: string;
  key: string;
  label: string;
}

/** Bottom-bar entry — the mobile bar is flat and allow-listed by route name. */
type NavMobileItem = NavLinkItem & { routeName: string };

export type { NavChildRouteItem, NavExternalItem, NavItem, NavLinkItem, NavMobileItem, NavRouteItem };
