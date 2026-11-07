<script setup lang="ts">
import { useSlots, useTemplateRef } from 'vue';
import { useViewport } from '@Composables';

/**
 * The page's title row, and the one place a screen's actions live.
 *
 * On a phone this is ONE row: the title, the screen's primary action, and a `⋮` holding
 * everything else. It used to stack into a column below `md` without exception, which on a screen
 * like Dicas — a short title and a single button — pushed the button onto a line of its own
 * with most of the width sitting empty beside it.
 *
 * THE ACTIONS SLOT IS RENDERED ONCE, NEVER TWICE (CRITICAL). Inline on a wide screen, inside the
 * overflow panel on a phone — chosen with `v-if` on `useViewport`, not by hiding one copy with
 * `md:hidden`. Two copies would mean two elements answering the same `data-testid` and two
 * components claiming the same template ref.
 *
 * That single rendering is also what keeps popovers working. An earlier note here rejected an
 * overflow menu because the actions anchor `ConfirmPopup`s, and a popover anchored to a button
 * inside a closed menu has nothing to attach to. Putting the real buttons inside the panel solves
 * it: the anchor exists exactly when it is needed, because the panel is open at the moment the
 * person clicks. The panel deliberately does NOT close on a click inside it — closing would pull
 * the anchor out from under the confirmation it just opened.
 */
const props = withDefaults(
  defineProps<{
    title: string;
    /**
     * Opt IN to the `⋮` on a phone. Off by default on purpose: a screen whose actions include
     * date navigation would be made worse by hiding a high-frequency
     * control behind a menu. Those screens keep their actions inline (now wrapping instead of
     * forced onto their own row) until each has been looked at and given a real primary action.
     */
    collapseActions?: boolean;
  }>(),
  { collapseActions: false }
);

const slots = useSlots();
const { isMobile } = useViewport();

const overflowPanel = useTemplateRef<{ toggle: (event: Event) => void }>('overflowPanel');

// HELPERS

/**
 * Answered on EVERY render, deliberately, and NOT a `computed` (CRITICAL).
 *
 * `slots` is a plain object. Vue writes into it when the parent re-renders and nothing tracks that
 * write, so a `computed` reading `slots.actions` keeps answering with whatever was true the first
 * time it was asked. A page whose actions ARRIVE LATER therefore never got its `⋮`: Novidades hangs
 * a read filter here that exists only once the list has loaded, the slot was empty at first paint,
 * and the filter stayed inline and pushed a 360px phone out to 421px. The same staleness ran the
 * other way on a screen whose actions leave while a dialog is open — the `⋮` stayed
 * behind, opening an empty panel over the dialog.
 *
 * A function called from the template re-reads the slot on every render, which is exactly when the
 * answer can change. `isMobile` is still tracked by the render effect, so a rotation still repaints.
 */
function isCollapsed() {
  return isMobile.value && props.collapseActions && Boolean(slots.actions);
}

// EVENTS

function onOverflowClick(event: Event) {
  overflowPanel.value?.toggle(event);
}
</script>

<template>
  <!-- `flex-nowrap` on a phone, and this is the load-bearing part.
       Row 1 must be ONE row: menu, title, and the actions this screen is for. Wrapping was a
       pixel race nobody wins — the row fit until a title ran two words long, or a font changed
       width, or a translation was longer than the one it was measured against. Nothing here can
       wrap now: the actions are `shrink-0` and the title block is `min-w-0 overflow-hidden`, so
       the title never pushes them down. The title itself is `whitespace-nowrap` and is NOT
       truncated (RULES, "Row 1 never wraps on a phone"): if it does not fit, the block clips it
       mid-letter, and that is the SCREEN's bug to fix — fewer inline actions (`collapseActions`)
       or a shorter title — not this component's. A header in two rows is a header that ate the
       screen.
       Wide screens keep wrapping, where a row of many actions genuinely wants a second line. -->
  <div class="flex flex-nowrap items-center justify-between gap-x-3 gap-y-2 md:flex-wrap">
    <!-- `basis-64` is what makes the row wrap instead of crushing the title. With `flex-1` alone
         the title block shrinks to whatever is left after the actions, which on a phone turned
         "Novidades › Uma entrada longa" into "N…". A preferred basis says how much the title would
         LIKE; `flex-1` still lets it grow into whatever the actions leave over, so the basis only
         decides when the row gives up and wraps.
         It is smaller on a phone (6rem) than on a wide screen (16rem) because a phone row has to
         fit a title, a primary action, a stopwatch and a menu in 348px. 6rem is the threshold, not
         the width: `flex-1` still grows the title into whatever the actions leave over, so on a
         roomier phone it is wider than this and the number never shows. -->
    <div class="flex min-w-0 flex-1 basis-24 items-center gap-2 overflow-hidden md:basis-64">
      <!-- `shrink-0` on everything in this block EXCEPT the title.
           Making the `h1` `whitespace-nowrap` left the menu button as the only flexible item in
           the row, so it absorbed all the crushing: 2px wide at 320 on Novidades and
           Permissões, and 6px at 390 in the narrow layout. It is the only control that reopens a
           collapsed rail, so squeezing it is squeezing the way out of the screen. The title is
           the one thing here that may give, and it is `overflow-hidden` so it clips instead of
           painting over the actions. -->
      <!-- `AMenuButton`, not an inline Button. It was inline while the bootstrap had no
           collapsible rail and the only job was opening the drawer; now the same control has to
           answer differently by width — fold the rail on desktop, open the drawer below `lg` —
           and it has to appear at every width when the person pinned it. That is behavior, and
           behavior belongs to the atom, which `TheLayout` also renders for a page that draws no
           header at all. -->
      <AMenuButton class="shrink-0" />

      <slot name="leading" />

      <h1
        data-page-title
        class="text-heading text-lg font-semibold tracking-tight whitespace-nowrap md:text-xl"
      >
        {{ $t(title) }}
      </h1>

      <!-- Sits after the title, for a page that has a current context worth naming.
           `shrink-0 whitespace-nowrap` for the same reason as the menu button: the season chip
           "T3 · S4" was folding onto three lines at 320 and taking the header's height from 42px
           to 71px, which is the two-row header this component exists to prevent. -->
      <div class="flex shrink-0 items-center gap-2 whitespace-nowrap">
        <slot name="titleSuffix" />
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <!-- Always visible, on any width: the one thing this screen is for. -->
      <slot name="primaryAction" />

      <div
        v-if="!isCollapsed()"
        class="flex flex-wrap items-center justify-end gap-2"
      >
        <slot name="actions" />
      </div>

      <template v-if="isCollapsed()">
        <Button
          text
          data-testid="page-header-overflow"
          icon="pi pi-ellipsis-v"
          severity="secondary"
          :aria-label="$t('common.moreActions')"
          @click="onOverflowClick"
        />

        <Popover ref="overflowPanel">
          <!-- `items-stretch`: an action in a menu reads as a row, not as a button floating in a
               panel, and a full-width row is a far better thumb target. -->
          <div class="flex min-w-[13rem] flex-col items-stretch gap-2">
            <slot name="actions" />
          </div>
        </Popover>
      </template>
    </div>
  </div>
</template>
