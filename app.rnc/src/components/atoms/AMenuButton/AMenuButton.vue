<script setup lang="ts">
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { DESKTOP_BREAKPOINT_PX } from '@Constants';
import useUiStore from '@Stores';

/**
 * The way back to the navigation.
 *
 * It is an atom rather than markup inside `ThePageHeader` because it is the ONLY way to
 * reopen a collapsed rail, and a page-header slot is optional — a view that renders no
 * header used to leave the navigation unreachable and the state persisted, which meant
 * clearing localStorage was the only way out. `TheLayout` renders one itself when the page
 * supplies no header, so the escape hatch never depends on what the page chose to draw.
 */
const uiStore = useUiStore();
const { isMenuButtonPinned, isSidenavOpen } = storeToRefs(uiStore);
const { toggleNavCollapsed, toggleSidenav } = uiStore;

const isDesktop = computed(() => uiStore.appWidth >= DESKTOP_BREAKPOINT_PX);

// EVENTS
/**
 * Wide screens have no drawer — ONavbarDrawer is `lg:hidden`, so asking for it there dims
 * the page behind a panel that never paints. On desktop the button folds the rail away and
 * brings it back; below `lg` it opens the drawer, which is the only navigation there is.
 */
function onMenuClick() {
  if (isDesktop.value) {
    toggleNavCollapsed();

    return;
  }

  toggleSidenav();
}
</script>

<template>
  <!-- Pinned, the button stays on screen at every width and the rail becomes something you
       open and close. Unpinned, it only appears in the tablet range, where the rail does not
       fit and the drawer is the whole navigation. -->
  <Button
    plain
    text
    data-testid="page-header-menu"
    icon="pi pi-bars"
    :aria-expanded="isSidenavOpen"
    :aria-label="$t('common.actions.menu')"
    :class="isMenuButtonPinned ? 'flex' : 'hidden md:flex lg:hidden'"
    @click="onMenuClick()"
  />
</template>
