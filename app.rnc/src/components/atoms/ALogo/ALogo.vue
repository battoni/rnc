<script setup lang="ts">
/**
 * Franco Contabilidade's text logo (rnc-1): no image, so it is never missing, never blurred and
 * always in the brand token. `full` is the wordmark, `min` the "F" tile for a narrow rail.
 * `tone="inherit"` lets the parent's colour cascade — a filled band, a dark panel. `tone="brand"` is
 * the brand as a WORD (`text-brand-word`): navy on paper, the theme's `--color-primary-text` on a
 * dark ground — `text-primary-500` was navy on the dark scheme's near-black header, 1.6:1.
 */
const props = withDefaults(
  defineProps<{
    tone?: 'brand' | 'inherit';
    variant?: 'full' | 'min';
  }>(),
  {
    tone: 'brand',
    variant: 'full',
  }
);

const PRODUCT_NAME = 'Franco Contabilidade';
</script>

<template>
  <span :class="['inline-flex items-center leading-none', { 'text-brand-word': props.tone === 'brand' }]">
    <span
      v-if="props.variant === 'min'"
      class="bg-primary-500 text-primary-contrast inline-flex aspect-square h-[1.75em] items-center justify-center rounded-md font-bold"
      role="img"
      :aria-label="PRODUCT_NAME"
    >
      F
    </span>

    <span
      v-else
      class="font-semibold tracking-tight whitespace-nowrap"
    >
      Franco <span class="font-normal">Contabilidade</span>
    </span>
  </span>
</template>
