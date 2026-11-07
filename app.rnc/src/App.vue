<script setup lang="ts">
import { onMounted } from 'vue';
import { RouterView } from 'vue-router';
import { useLocaleFromQuery } from '@Composables';
import useUiStore, { useColorSchemeStore } from '@Stores';

const colorSchemeStore = useColorSchemeStore();
const uiStore = useUiStore();

// `?locale=` is read here, once: the app has exactly one router and one global locale.
useLocaleFromQuery();

onMounted(onComponentMount);

// EVENTS
function onComponentMount() {
  colorSchemeStore.init();
  uiStore.init();
}
</script>

<template>
  <Toast />

  <TheRouteGuard>
    <!-- `out-in`, not a true cross-fade: two full-height layouts in flow at once shove each
         other down the page, which is the jump the fade exists to remove. The leave is `quick`
         because the two halves are serial — a route a person walks through all day cannot cost
         `calm` twice — and the arrival, which is the half that gets watched, is `calm`.
         The fade is named, not utility classes, because it must NOT paint the whole view: the
         style block below scopes the opacity to `[data-route-fade]`, the element each layout
         marks as "the part that goes somewhere". In `TheLayout` that is the content column, so
         the navigation rail rides through the swap at full opacity and reads as furniture that
         never left the room.

         Do not drop out-in for a simultaneous fade + absolute leave: that combo invented thin
         vertical and horizontal scrollbars on every settled page (DevTools 360×800). NotFound →
         Home blank is fixed at the source with a named `push` in NotFoundView, not by changing
         this Transition. -->
    <RouterView v-slot="{ Component }">
      <Transition
        mode="out-in"
        name="route-fade"
      >
        <component :is="Component" />
      </Transition>
    </RouterView>
  </TheRouteGuard>
</template>

<style>
/*
 * Deliberately unscoped: the Transition classes land on each view's ROOT, while the element
 * that actually fades is a descendant the layout marks with `data-route-fade` (or the root
 * itself, in a layout that has no navigation to hold steady). The rail is a sibling of the
 * marked element, so during the out-in swap the outgoing rail stays opaque to its last frame
 * and the incoming one paints identical pixels in the same flush — the remount is invisible,
 * and only the content is seen to breathe. A view whose layout marks nothing swaps with no
 * fade at all, which is the honest fallback, never a whole-view fade that would take the rail.
 */
.route-fade-enter-active[data-route-fade],
.route-fade-enter-active [data-route-fade] {
  transition: opacity var(--motion-calm) var(--motion-ease-settle);
}

.route-fade-leave-active[data-route-fade],
.route-fade-leave-active [data-route-fade] {
  transition: opacity var(--motion-quick) var(--motion-ease-settle);
}

.route-fade-enter-from[data-route-fade],
.route-fade-enter-from [data-route-fade],
.route-fade-leave-to[data-route-fade],
.route-fade-leave-to [data-route-fade] {
  opacity: 0;
}
</style>
