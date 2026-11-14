<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type {
  NavChildRouteItem,
  NavExternalItem,
  NavItem,
  NavLinkItem,
  NavMobileItem,
  NavRouteItem,
} from './interfaces';
import type { RouteParamsRawGeneric } from 'vue-router';
import type { RouteLocationRaw } from 'vue-router';
import { useFirmBrand, useViewport } from '@Composables';
import { DOCS_URL } from '@Constants';
import { HTTP_STATUS } from '@Enums';
import { getI18nRouteName, getLoginPath } from '@Helpers';
import { resetAllStores } from '@Libraries';
import useUiStore from '@Stores';
import { LogoutService } from '@AuthModule';
import { useUserStore } from '@UserModule';
import { useNavBadges } from './navBadges.local';
import { localNavSettings, useLocalNavItems } from './navItems.local';

const uiStore = useUiStore();
const userStore = useUserStore();

const localItems = useLocalNavItems();
const { forget: forgetFirmBrand } = useFirmBrand();
const { isMobile: isMobileView } = useViewport();
const { t } = useI18n();

// Which entries wear an attention dot. Local extension point — the nav never imports a module.
const navBadges = useNavBadges();
const { push, resolve } = useRouter();
const toast = useToast();

/** Routes allowed into the bottom bar. Children never appear there — the bar stays flat. */
const MOBILE_ROUTE_NAMES = ['home', 'users', 'team-home', 'management-home', 'operator-home'];

/** The core entries; the local layer's own are appended and may be store-backed. */
const CORE_NAV_ITEMS: NavItem[] = [{ icon: 'pi pi-home', label: 'navigation.home', routeName: 'home' }];

/*
 * The footer is the local layer's (`localNavSettings`) — personalization is its own link below.
 * Retired (rnc-4, Chrome QA 5.2): the core "Usuários" and "Permissões" entries. The hub's rail declares
 * them in the operator's group (`navItems.local`, on the operator's role), so the operator saw both twice,
 * and a firm manager — who holds `users.read` for its team's logins — saw "Usuários" here.
 */
const settings = ref<NavItem[]>([...localNavSettings]);

const drawerClass = computed(() => (isMobileView.value ? 'nav-drawer-mobile' : 'w-54'));
const drawerPosition = computed(() => (isMobileView.value ? 'full' : 'left'));
const itemsWithRoutes = computed(() => toVisibleItems([...CORE_NAV_ITEMS, ...localItems.value]));
const mobileItems = computed(() => itemsWithRoutes.value.filter(isMobileNavItem));
// `false` would ship as inert="false", which HTML reads as inert. Absent is the only "off".
const railInert = computed(() => uiStore.isNavCollapsed || undefined);
// The settings block renders flat links only, so a settings entry must carry its own route.
const settingsWithRoutes = computed(() => toVisibleItems(settings.value).filter(isLinkNavItem));

/*
 * The documentation portal, when the environment names one. A project without a portal gets no
 * entry at all — never a link that goes nowhere, which is what a hard-coded URL with an empty
 * value produces.
 */
const docsLink = computed<NavExternalItem | null>(() =>
  DOCS_URL ? { href: DOCS_URL, icon: 'pi pi-book', key: 'docs', label: 'navigation.docs' } : null
);

const personalizationWithRoute = computed(() => ({
  icon: 'pi pi-cog',
  key: 'personalization',
  label: 'navigation.personalization',
  routeName: 'personalization',
  route: resolve({ name: getI18nRouteName('personalization') }).path,
}));

// HELPERS
function closeTabletDrawer() {
  uiStore.closeSidenav();
}

function isLinkNavItem(item: NavRouteItem): item is NavLinkItem {
  return !!item.route;
}

function isMobileNavItem(item: NavRouteItem): item is NavMobileItem {
  return !!item.route && !!item.routeName && MOBILE_ROUTE_NAMES.includes(item.routeName);
}

function isVisibleNavItem(item: NavItem): boolean {
  const isPermissionDenied = !!item.permission && !userStore.hasPermission(item.permission);
  const isRoleDenied = !!item.role && userStore.user?.role_slug !== item.role;

  return !isPermissionDenied && !isRoleDenied;
}

/** Children go through the same gate and resolution as their parents — one level deep, links only. */
function resolveChildren(children?: NavItem[]): NavChildRouteItem[] {
  if (!children) return [];

  return children.filter(isVisibleNavItem).flatMap((child) => {
    if (!child.routeName) return [];

    return [
      {
        icon: child.icon,
        isLabelLiteral: child.isLabelLiteral,
        key: child.key ?? child.routeName,
        label: child.label,
        route: resolveRoute(child.routeName, child.params),
        routeName: child.routeName,
      },
    ];
  });
}

/** Home keeps its literal name — the i18n helper is for the localized routes only. */
function resolveRoute(routeName: string, params?: RouteParamsRawGeneric): RouteLocationRaw {
  return routeName === 'home' ? { name: 'home' } : resolve({ name: getI18nRouteName(routeName), params }).path;
}

/**
 * Permission/role filter plus route resolution, at both levels. The declared shape decides the
 * behavior downstream: route only → plain link; children only → expander; both → link with a
 * toggle. A group whose children were all filtered out and that has no route of its own leads
 * nowhere, so it never renders.
 */
function toVisibleItems(navItems: NavItem[]): NavRouteItem[] {
  return navItems.filter(isVisibleNavItem).flatMap((item) => {
    const children = resolveChildren(item.children);
    const route = item.routeName ? resolveRoute(item.routeName, item.params) : undefined;

    if (!route && !children.length) return [];

    return [
      {
        children: children.length ? children : undefined,
        icon: item.icon,
        isLabelLiteral: item.isLabelLiteral,
        key: item.key ?? item.routeName ?? item.label,
        label: item.label,
        hasDot: item.routeName ? navBadges.value[item.routeName] : undefined,
        route,
        routeName: item.routeName,
      },
    ];
  });
}

// EVENTS

/**
 * Clearing the user is not clearing the app: the feature stores still hold the lists,
 * drafts and counters that belonged to whoever just left, and they guard
 * their own fetches with "already loaded" flags — so the next person to sign in inside
 * this same SPA session saw the previous one's data until a reload. `resetAllStores`
 * exists for exactly this and was never called; it skips `ui` (device preferences) and
 * `colorScheme` re-reads localStorage, so the theme and layout survive.
 *
 * A 401 on logout is the same destination: the server already has no session, so treating
 * it as failure kept the person on a screen that looked signed in with a toast that lied.
 *
 * The host firm's kept brand goes too (Chrome QA 5.21): at the root it is the session's answer — a
 * view-as's firm — and the login that follows is the platform's.
 */
function completeLogout() {
  push(getLoginPath());

  userStore.clearUser();
  resetAllStores();
  forgetFirmBrand();

  toast.add({
    severity: 'success',
    summary: t('auth.seeYouSoon'),
    life: 5000,
  });
}

function onLogout() {
  LogoutService()
    .then(completeLogout)
    .catch((error: { response?: { status?: number } }) => {
      if (error?.response?.status === HTTP_STATUS.UNAUTHORIZED) {
        completeLogout();
        return;
      }

      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        life: 5000,
      });
    });
}

function onToggleDrawer() {
  uiStore.toggleSidenav();
}

function onToggleNavGroup(groupKey: string) {
  uiStore.toggleNavGroup(groupKey);
}
</script>

<template>
  <div
    :class="[
      'motion-calm motion-ease-settle grid w-0 shrink-0 overflow-visible transition-[grid-template-columns] lg:w-auto lg:shrink-0',
      uiStore.isNavCollapsed ? 'grid-cols-[0fr]' : 'grid-cols-[1fr]',
    ]"
  >
    <!-- The rail is a grid track, not a `v-if`. Mounting and unmounting it made the whole page
         jump sideways — the largest layout shift in the app — and `width` cannot be animated out
         of `auto`. A single `1fr` column can: on an intrinsically sized grid container the track
         resolves to the rail's max-content width, so `1fr → 0fr` interpolates the real width with
         nothing hard-coded. The rail stays mounted and `inert` instead — hidden but never lost,
         the drawer below is still reachable from the page header. The fade keeps the clipping
         edge from cutting through text. -->
    <!-- `-mx-3 px-3` only from `lg`, where the desktop rail is visible: the clip that makes the
         collapse animation possible also cut the rail's relief off at its own right edge.
         Below `lg` the track is `w-0` and this wrapper holds no in-flow chrome — negative margins
         still expanded past the viewport and bought a few pixels of horizontal scroll (which then
         dragged a vertical scrollbar with it). Horizontal only when applied: the rail is exactly
         100dvh tall, so vertical padding would clip its last item instead of its shadow. -->
    <div
      :class="[
        'motion-calm min-w-0 overflow-hidden transition-opacity lg:-mx-3 lg:px-3',
        { 'opacity-0': uiStore.isNavCollapsed },
      ]"
      :inert="railInert"
    >
      <ONavbarDesktop
        :docsLink
        :expandedGroupKeys="uiStore.expandedNavGroups"
        :itemsWithRoutes
        :personalizationWithRoute
        :settingsWithRoutes
        @logout="onLogout"
        @toggle-group="onToggleNavGroup"
      />
    </div>

    <ONavbarDrawer
      :docsLink
      :drawerClass
      :drawerPosition
      :expandedGroupKeys="uiStore.expandedNavGroups"
      :itemsWithRoutes
      :personalizationWithRoute
      :settingsWithRoutes
      :visible="uiStore.isSidenavOpen"
      @close="closeTabletDrawer"
      @logout="onLogout"
      @toggle-group="onToggleNavGroup"
      @update:visible="uiStore.isSidenavOpen = $event"
    />

    <ONavbarMobile
      :mobileItems
      :personalizationWithRoute
      @toggle-drawer="onToggleDrawer"
    />
  </div>
</template>

<style scoped>
@reference "../../../styles/main.css";

.router-link-exact-active {
  @apply font-semibold;
}

/* primary-100 keeps the same peach in both schemes, so its text pairs inside the same ramp —
   primary-950 — instead of a role token that flips against it in dark. */
.router-link-exact-active .pi {
  @apply bg-primary-100 text-primary-950 font-semibold;
}

::deep(.nav-drawer-mobile) {
  height: 100vh;
  max-height: 100vh;
}
</style>
