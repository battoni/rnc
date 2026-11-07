<script setup lang="ts">
import { computed } from 'vue';
import useUiStore from '@Stores';
// Imported, against the auto-import rule: the `.local.vue` name is not a component name the
// resolver registers, and the file has to stay recognisable as a project's LOCAL half.
import TheLocalOverlays from './TheLocalOverlays.local.vue';

defineProps<{
  classes?: string;
}>();

const uiStore = useUiStore();

/*
 * The narrow layout used to be a flat `px-8` at every width, which on a 390px phone spent 64px —
 * a sixth of the screen — on margin, and pushed the header row into two lines. It steps up now,
 * the way the fullscreen scale already did: 24px on a phone, the original 32px from `md`.
 */
const contentPaddingClass = computed(() => (uiStore.isFullscreen ? 'px-3 sm:px-4 md:px-6 lg:px-8' : 'px-6 md:px-8'));

/*
 * The narrow layout's width ladder, and the widest the rail + content ensemble is ever drawn.
 *
 * The top step is 1448 — the 1200px reading column plus the rail's 248px footprint — and NOT the
 * 1536 a 2xl breakpoint would otherwise allow. That is the centring fix. The outer wrapper is
 * centred and the ensemble left-aligns inside it, so any width the outer wrapper has that the
 * ensemble cannot use is stranded on the RIGHT: at 1536 that was 88px, and the page sat 192px from
 * the left edge against 280 from the right. Both numbers come from the same two places now, so
 * they cannot agree at 1440 and drift apart at 1920.
 */
const NARROW_WIDTH_LADDER = 'sm:max-w-[640px] md:max-w-[768px] lg:max-w-[1024px] xl:max-w-[1280px] 2xl:max-w-[1448px]';

/*
 * With the rail present the column hugs it and leftover width falls to the right — see the
 * template. Collapsed, there is nothing to hug, so the column takes the whole outer wrapper.
 *
 * It used to take `max-w-[1448px]` = 1200 + the rail's 248px footprint, which held the right edge
 * still as the rail went away. That was right about the right edge and wrong about everything to
 * the right OF it: at 2xl the outer wrapper allows 1536, so an 1448 column left-aligned inside it
 * stranded 88px, and the page read as 192px of margin on the left against 280 on the right. Two
 * caps computed from different things, one from the rail and one from the breakpoint, and they
 * only agreed by accident at 1440.
 *
 * Sharing the ladder makes them agree at every width, and both caps stay numeric so
 * `transition-[max-width]` still animates the collapse.
 */
const innerMaxWidthClass = computed(() => {
  if (uiStore.isFullscreen) return 'max-w-none';

  return uiStore.isNavCollapsed ? NARROW_WIDTH_LADDER : 'max-w-[1200px]';
});

const outerMaxWidthClass = computed(() => (uiStore.isFullscreen ? 'max-w-none' : NARROW_WIDTH_LADDER));
</script>

<template>
  <div class="flex h-full min-h-0 w-full justify-center">
    <div :class="['flex w-full', outerMaxWidthClass]">
      <TheNavbar />

      <!-- No max-height here. The column is contained by `#app` at the viewport height, this
           `flex-1`, and `min-h-0` — the cap that used to sit here could only ever make it
           SHORTER than the space it was given, never contain anything. `calc(120vh-10rem)` binds
           on any viewport under 800px tall, so a laptop screen ended the content column short of
           the page bottom while the navigation beside it, sized from `100dvh`, did not. -->
      <!-- No mx-auto here: the OUTER wrapper already centres the nav+content ensemble, and a
           second centring floated the column away from the rail — 76px where the rhythm says 32.
           The column hugs the rail at the standard gap; leftover width falls to the right. -->
      <!-- transition-[max-width] runs on the same motion-calm/settle pair as the rail's
           grid-track collapse in TheNavbar, so the cap grows exactly as fast as the rail
           frees the space — the column's width is continuous, whichever constraint binds. -->
      <!-- `data-route-fade` marks this column as the part of the page that travels between
           routes: App.vue's route-fade scopes its opacity fade to this element, so the rail
           beside it holds steady through the out-in swap. Keep the marker on the COLUMN, not
           the root — on the root it would take the navigation with it, which is the exact
           bug the scoped fade replaces. -->
      <div
        data-route-fade
        :class="[
          'motion-calm motion-ease-settle flex min-h-0 w-full flex-1 flex-col transition-[max-width]',
          innerMaxWidthClass,
        ]"
      >
        <div
          v-if="$slots['pageHeader']"
          :class="[contentPaddingClass, 'pt-8']"
        >
          <slot name="pageHeader" />
        </div>

        <!-- A page with no header still needs the way back to the navigation: the menu button
             is the ONLY thing that reopens a collapsed rail, and that state is persisted, so
             a headerless page used to strand the app with no route out but localStorage. -->
        <div
          v-else
          :class="[contentPaddingClass, 'pt-8']"
        >
          <AMenuButton />
        </div>

        <main :class="['relative flex min-h-0 w-full flex-1 transform-[translateZ(0)] flex-col', classes]">
          <div
            :class="[
              // `lg`, matching ONavbarMobile: the bar is on screen until the rail arrives at 1024, so the
              // band that keeps content off it has to last exactly as long. They were `md` and `lg`,
              // which left 768–1023 with content sitting under the bar.
              'main-scroll flex flex-1 flex-col gap-8 overflow-y-auto pt-8 pb-[var(--mobile-nav-band)] lg:pb-8',
              contentPaddingClass,
            ]"
          >
            <slot />
          </div>

          <!-- The project's own overlays: a LOCAL file the sync never touches, empty in rnc. -->
          <TheLocalOverlays />
        </main>
      </div>
    </div>
  </div>
</template>
