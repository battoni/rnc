<script setup lang="ts">
import { computed } from 'vue';
import type { NavLinkItem, NavMobileItem } from '../../../interfaces';
import useUiStore from '@Stores';

defineProps<{
  mobileItems: NavMobileItem[];
  personalizationWithRoute: NavLinkItem;
}>();

const emit = defineEmits<{
  'toggle-drawer': [];
}>();

const uiStore = useUiStore();

// The bar is fixed to the viewport, so nothing in the layout constrains it: pinned to
// `left-0 right-0` it spanned the whole window while the page content sat inside a capped,
// padded column, and its edges ran past the cards by 16px on a phone and 70px at the tablet
// breakpoint. These two mirror TheLayout's own wrapper cap and content padding for the range
// the bar lives in (< lg) — the cap centres the root, the padding then insets the surface,
// which is the order TheLayout builds the column in. Keep them in step with
// `outerMaxWidthClass` / `contentPaddingClass` there.
/*
 * The SAME ladder `TheLayout` gives the content column, not just its first step.
 *
 * A flat `sm:max-w-[640px]` was correct while the bar was `md:hidden` — only the `sm` step could
 * ever apply. Moving it to `lg:hidden` (the rail arrives at `lg`, so 768–1023 had no persistent
 * navigation at all) put it on screen at widths where the column is 768 and 1024 wide and the bar
 * was still capped at 640: on an iPad in portrait the bar was 128px narrower than the page above
 * it, and its outer icons no longer lined up with the page's own edges.
 */
const barMaxWidthClass = computed(() =>
  uiStore.isFullscreen ? 'max-w-none' : 'sm:max-w-[640px] md:max-w-[768px] lg:max-w-[1024px]'
);
/*
 * `px-6` on a phone in narrow mode, not `px-8`, and stepping at `md` — the same breakpoint
 * `TheLayout` steps at. A flat `px-8` took 64px out of a 360px bar and cut the last destination in
 * half; stepping at `sm` instead put the bar on `px-8` while the content above it was still
 * `px-6`, from 640 to 767.
 */
const barPaddingClass = computed(() => (uiStore.isFullscreen ? 'px-3 sm:px-4' : 'px-6 md:px-8'));

// EVENTS
function onToggleDrawer() {
  emit('toggle-drawer');
}
</script>

<template>
  <!-- `--mobile-nav-band` is the ONE definition of the space this bar occupies, shared with
       TheLayout's scroller reserve so the canvas ends exactly where content is allowed to start
       again — a taller backdrop would paint over the last line of the page, a shorter one would
       let the page show through beneath the bar. Defined in reset.css, where the comment explains
       why it is a `max()` and not a sum. -->
  <div
    class="mobile-navbar-backdrop canvas-atmosphere bg-canvas pointer-events-none fixed right-0 bottom-0 left-0 z-40 h-[var(--mobile-nav-band)] lg:hidden"
  />

  <!-- `mx-auto` is what makes the cap centre a fixed element: with both `left-0` and `right-0`
       set and a max-width smaller than the window, the auto inline margins split the slack, so
       the root lands on the page column's box. The padding then insets the surface to the
       content's own edges.
       The bottom gap is `--mobile-nav-gap`, which is `max(float, safe-area)` and NOT their sum.
       Adding them is what put this bar visibly too high in Firefox Android, which reports an
       inset where Chrome Android reports none: the person got the 16px float AND the inset, and
       a band of empty canvas underneath. -->
  <div
    :class="[
      'mobile-navbar-root fixed right-0 bottom-0 left-0 z-50 mx-auto flex pb-[var(--mobile-nav-gap)] lg:hidden',
      barMaxWidthClass,
      barPaddingClass,
    ]"
  >
    <div class="bg-panel shadow-lift-lg flex w-full min-w-0 items-center justify-center gap-3 rounded-xl px-2 py-3">
      <!-- `min-h-11 min-w-11` = 44px: the phone's primary navigation control is DRAWN at 44, not
           only reached at 44, so it grows its real box rather than taking `touch-target`.
           (`touch-target` once collapsed this button to 12×12 by taking over PrimeVue's `::after`
           spacer; the growth is a `::before` now and that no longer happens — celer-06.) -->
      <Button
        outlined
        plain
        class="min-h-11 min-w-11 shrink-0"
        icon="pi pi-bars"
        @click="onToggleDrawer"
      />

      <RouterLink
        class="hover:bg-panel-muted motion-instant touch-target xs:p-1 xs:px-2 flex shrink-0 flex-row items-center justify-center rounded-sm p-0.5 px-1 transition-colors"
        exact-active-class="bg-primary-100 text-primary-950"
        :aria-label="$t(personalizationWithRoute.label)"
        :to="personalizationWithRoute.route"
      >
        <span :class="['xs:p-2 xs:text-xl rounded-sm p-1.5 text-lg', personalizationWithRoute.icon]" />
      </RouterLink>

      <!-- No `flex-1`: the scroller takes only the width its links need, so the parent's
           `justify-center` centres the whole bar instead of pinning the icons left against an
           empty right half. `min-w-0` keeps the default shrink alive, so a long allow-list
           still narrows this box and scrolls inside it rather than stretching the bar.
           `gap-3` between links whose hit areas each grow 6px: at `gap-0.5` they overlapped by
           10px and the later link won. `-m-1.5 p-1.5` gives that growth room INSIDE the scroll
           box — `overflow-x-auto` clips its content on both axes, so without it a link was 34px
           tall to a thumb — while the negative margin keeps the bar's height and spacing. -->
      <div class="-m-1.5 flex min-w-0 flex-nowrap items-center gap-3 overflow-x-auto p-1.5">
        <!-- The padding was INVERTED: `p-2 text-xl` was the base and `min-[480px]:p-1.5` the
             override, so the narrowest screens got the LARGEST icons and this strip scrolled from
             348px all the way past 430px. Mobile-first now — compact by default, roomier once
             there is room — which is what stops the bar scrolling at all. -->
        <RouterLink
          v-for="{ icon, route, routeName } in mobileItems"
          class="hover:bg-panel-muted motion-instant touch-target xs:gap-1.5 xs:p-1.5 xs:px-2 flex min-w-0 shrink-0 flex-row items-center justify-center rounded-sm p-0.5 px-1 transition-colors"
          exact-active-class="bg-primary-100 text-primary-950"
          :key="routeName"
          :to="route"
        >
          <span :class="['xs:p-2 xs:text-xl rounded-sm p-1.5 text-lg', icon]" />
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style>
body:has(.p-dialog-mask) .mobile-navbar-backdrop,
body:has(.p-dialog-mask) .mobile-navbar-root {
  display: none;
}
</style>
