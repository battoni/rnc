<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import type { ButtonProps } from 'primevue/button';

type ActionButtonProps = Pick<ButtonProps, 'severity' | 'variant'> & { label: string };

const emit = defineEmits<{
  onClose: [];
  onSubmit: [];
}>();

withDefaults(
  defineProps<{
    blockScroll?: boolean;
    closable?: boolean;
    closeOnEscape?: boolean;
    contentClass?: string;
    dismissableMask?: boolean;
    draggable?: boolean;
    footerClasses?: string;
    headerClasses?: string;
    isFooterless?: boolean;
    isHeadless?: boolean;
    modal?: boolean;
    position?: 'bottom' | 'center' | 'left' | 'right' | 'top';
    title?: string;
    cancelButtonProps?: ActionButtonProps;
    submitButtonProps?: ActionButtonProps;
  }>(),
  {
    blockScroll: true,
    closable: true,
    closeOnEscape: false,
    contentClass: 'flex min-h-0 flex-col gap-4',
    /*
     * `false`, and it was `true` while doing NOTHING. PrimeVue closes on an outside tap only when
     * `dismissableMask && modal`, and `modal` is `false` here on purpose — so no dialog ever closed
     * that way, while the default and the RULES both said it did. The behaviour is the right one:
     * these dialogs hold half-filled forms. It still works for a caller that opts into `modal`.
     */
    dismissableMask: false,
    draggable: false,
    footerClasses: 'flex justify-end gap-4',
    headerClasses: 'flex items-center justify-center',
    modal: false,
    position: 'bottom',
    cancelButtonProps: () => ({ variant: 'outlined', severity: 'secondary', label: 'common.actions.cancel' }),
    submitButtonProps: () => ({ severity: 'primary', label: 'common.actions.submit' }),
  }
);

const visibleModel = defineModel<boolean>('visible', { default: false });

const contentWrapper = useTemplateRef<HTMLElement>('contentWrapper');

const dialogClass = computed(() => ['main-dialog-root w-full border-none shadow-none']);

// EVENTS
function onDialogShow() {
  const scrollEl = contentWrapper.value?.closest('.p-dialog-content') as HTMLElement | null;
  if (scrollEl) scrollEl.scrollTop = 0;
}
</script>

<template>
  <Dialog
    v-model:visible="visibleModel"
    appendTo="self"
    pt:content="scrollbar-brand"
    pt:headerActions="absolute right-2.5 top-3.5"
    pt:pcCloseButton:root:class="pointer-coarse:size-11"
    :blockScroll
    :class="[dialogClass, 'h-full max-h-full']"
    :closable
    :closeOnEscape
    :dismissableMask
    :draggable
    :modal
    :position
    :pt:footer="footerClasses"
    :pt:header="headerClasses"
    :showHeader="!isHeadless"
    @show="onDialogShow"
  >
    <template
      v-if="!isHeadless"
      #header
    >
      <div
        v-if="title && !$slots.header"
        class="flex items-center justify-center"
      >
        <h2 class="text-heading text-xl font-semibold">
          {{ $t(title) }}
        </h2>
      </div>

      <slot
        v-if="$slots.header"
        name="header"
      >
      </slot>
    </template>

    <div
      ref="contentWrapper"
      :class="['main-dialog-content-wrapper', contentClass]"
    >
      <slot />
    </div>

    <template
      v-if="!isFooterless"
      #footer
    >
      <template v-if="!$slots.footer">
        <div class="sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
          <Button
            v-bind="cancelButtonProps"
            :label="$t(cancelButtonProps.label)"
            @click="emit('onClose')"
          />

          <Button
            v-bind="submitButtonProps"
            :label="$t(submitButtonProps.label)"
            @click="emit('onSubmit')"
          />
        </div>
      </template>

      <slot
        v-else
        name="footer"
      />
    </template>
  </Dialog>
</template>

<style>
.p-dialog-bottom .main-dialog-root {
  margin-bottom: 0 !important;
}

.p-dialog-bottom .main-dialog-root.p-dialog-enter-active {
  animation: celer-dialog-enter-bottom var(--motion-settle) var(--motion-ease-settle);
}

.p-dialog-bottom .main-dialog-root.p-dialog-leave-active {
  animation: celer-dialog-leave-bottom var(--motion-settle) var(--motion-ease-settle);
}

/* The mask is the only thing standing between the sheet and the page, and it painted nothing at
   all: `modal: false` is a deliberate default here, and it is exactly what makes PrimeVue withhold
   the `p-overlay-mask` class that would have given the mask a background. A pane of glass is
   invisible for as long as the sheet covers the whole pane — and the sheet does not. It keeps the
   page's 32px side rhythm (below), while the page's own horizontal padding is 24px from `md` to
   `lg` in the full-width layout. The two do not track each other, so at 768 the cards underneath
   stuck out 8px past the sheet and showed as a rounded edge sliding down each gutter. Measured:
   mask 768 wide, sheet 704, 32px of glass on each side.

   Painting the mask fixes it at every width instead of at the one width where the two numbers
   happen to agree, and it keeps the gutters — a sheet that fills its mask edge to edge stops
   reading as a sheet on a desk, which is the whole point of the inset at 1280px.

   `--color-canvas` and not a dark scrim: this mask is scoped to <main>, so it butts straight onto
   the page header, which is canvas. A scrim would draw a dark frame around a sheet that is meant
   to rest ON the page, and it would end in a hard line under the title. Canvas makes the gutters
   and the 10px top seam read as the page's own ground — which is what they are.

   From `md` only. Below it the sheet is the whole screen (last rule in this file), so the mask has
   no edge left to show; painting it there would only blank the page behind the rising sheet.

   Paint, not capture: PrimeVue writes `pointer-events` inline on the mask from the `modal` prop,
   so the gutters still pass clicks to the page beneath. That is unchanged behaviour, and the
   pixels involved are 32px of page padding — flipping `modal` to close the gap would trade a
   sliver for a focus trap, an escape handler and a scroll lock nobody asked for. */
@media (min-width: 768px) {
  .p-dialog-mask:has(.main-dialog-root) {
    animation: celer-dialog-mask-enter var(--motion-settle) var(--motion-ease-settle);
    background: var(--color-canvas);
  }

  /* The fill arrives and leaves with the sheet. Painted outright it would blank the page half a
     second before the sheet got there and snap it back half a second after the sheet left. It is
     the BACKGROUND that animates and not the mask's opacity — opacity would take the sheet with
     it. `forwards` holds the drained state for the frames between the animation ending and
     PrimeVue removing the element. */
  .p-dialog-mask:has(.main-dialog-root.p-dialog-leave-active) {
    animation: celer-dialog-mask-leave var(--motion-settle) var(--motion-ease-settle) forwards;
  }
}

.main-dialog-root .main-dialog-content-wrapper {
  min-height: 0;
}

/* The dialog keeps the page's own 32px rhythm at the sides — its left edge lands exactly on the
   page content's. The bottom stays on the floor: this is a sheet that rises from it (see the
   `margin-bottom: 0` above), and a gap there would read as a card that failed to land. */
.main-dialog-root.p-dialog {
  margin: calc(var(--spacing) * 8);
}

/* A phone has no width to give away. The 32px inset above reads as a sheet floating on a desk at
   1280px and as a wasted 64px at 360px, where the form inside is already fighting for room — the
   week-pattern dialog's hour fields were the first casualty. Edge to edge below `md`; the vertical
   margins are left alone, because the top gap and the zeroed bottom are what make it a sheet. */
@media (max-width: 767px) {
  .main-dialog-root.p-dialog {
    margin-left: 0;
    margin-right: 0;
    width: 100vw;
    max-width: 100vw;
  }
}

/* The sheet FILLS its mask; it does not size to its content. Hiding the page and leaving only
   the page title standing is what this component is for, and a short form that stops halfway up
   leaves the page's own cards showing beneath that title. `max-height` cannot do this — it caps
   a tall dialog but never raises a short one — so the height is set outright.

   The top margin is 10px and no more. `appendTo="self"` scopes the mask to <main>, so the mask
   already starts where the page content starts — a large margin here would not buy air under the
   page title, it would re-expose the first rows of content through the gap. Ten pixels is a seam,
   not a window. Air between the title and the sheet's own heading is the header's padding below.

   Scoped to `.p-dialog-bottom`: a filled height only reads right against the zeroed bottom
   margin above, and every MMainDialog uses the default `bottom` position. */
.p-dialog-bottom .main-dialog-root.p-dialog {
  /* 10px of air at the top so the sheet reads as arrived rather than jammed under the header,
     and the height gives back exactly what the margin took: the bottom edge stays on the floor.
     Growing the height instead would push the sheet past the mask, and the first casualty is the
     strip that covers the scroll — the leak looks like a filler bug and is a height bug. */
  margin-top: 10px;
  height: calc(100% - 10px);
  max-height: calc(100% - 10px);
}

/* Header → content: the standard 32px rhythm (`gap-8`, same as TheLayout's <main>).
   The preset's only spacing here is the header's `overlay.modal.padding` (1.25rem) —
   the content has zero top padding — so the title sits too close to the body. */
.main-dialog-root .p-dialog-header {
  padding-bottom: calc(var(--spacing) * 8);
  padding-top: calc(var(--spacing) * 8);
}

/* Footer takes the sheet/mask tone (`--color-canvas`) so it does not read as a seam under the
   body — especially in dark, where panel (`#141413`) sat as a grey bar on canvas (`#0a0a09`).
   The old rule ("overlays are panels, so the footer must be panel") was wrong here: this sheet's
   mask is already canvas, and the footer has to disappear into it, not look like a second surface. */
.main-dialog-root .p-dialog-footer {
  background: var(--color-canvas);

  /* The footer is opaque, spans the full width and ends exactly on the sheet's bottom edge — but
     its corners are square where the sheet's are rounded, and the sheet does not clip them away:
     `overflow: hidden` there would also clip the absolutely-positioned close button and any focus
     ring. So the footer fills its square corner out past the arc of the sheet's 1px ring, and the
     ring reads as a light line cutting across a solid corner. Only dark shows it — in light the
     ring is the same value as what sits behind it, which is how it survived this long.

     `inherit`, never 26px: the media query at the end of this file squares the sheet off on a
     phone, and a hard-coded radius would round the very corners that rule exists to flatten. */
  border-bottom-left-radius: inherit;
  border-bottom-right-radius: inherit;
}

main:has(.main-dialog-root) .main-scroll {
  overflow: hidden !important;
}

@keyframes celer-dialog-enter-bottom {
  from {
    transform: translate3d(0, 100%, 0);
  }
}

@keyframes celer-dialog-leave-bottom {
  to {
    transform: translate3d(0, 100%, 0);
  }
}

@keyframes celer-dialog-mask-enter {
  from {
    background-color: transparent;
  }
}

@keyframes celer-dialog-mask-leave {
  to {
    background-color: transparent;
  }
}

/* Whole screen on a phone: no margin, no rounding, the full dynamic viewport. Placed LAST on
   purpose: the `.p-dialog-bottom` rules above set their own margin and height at the same
   specificity, so only source order decides. `appendTo` stays `self`, so the mask is still scoped
   to <main>, which starts below the page header; the sheet is anchored to the mask's bottom and is
   `100dvh` tall, so it rises past the mask's top and covers the header as well. `dvh` and not `vh`
   for the same reason as everywhere else: the browser chrome is part of a phone's viewport. */
@media (max-width: 767px) {
  .p-dialog-bottom .main-dialog-root.p-dialog {
    margin: 0;
    width: 100%;
    max-width: 100%;
    height: 100dvh;
    max-height: 100dvh;
    border-radius: 0;
  }
}
</style>
