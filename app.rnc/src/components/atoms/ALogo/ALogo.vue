<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * The firm's text logo (rnc-1): no image, so it is never missing, never blurred and always in the
 * brand token. `full` is the wordmark, `min` the initial's tile for a narrow rail.
 * `tone="inherit"` lets the parent's colour cascade — a filled band, a dark panel. `tone="brand"` is
 * the brand as a WORD (`text-brand-word`): navy on paper, the theme's `--color-primary-text` on a
 * dark ground — `text-primary-500` was navy on the dark scheme's near-black header, 1.6:1.
 * `name` is the firm's logo text, or a sub-brand's on the public site (rnc-3 R3), in the same mark:
 * the first word in the heavier weight, the rest in the regular one. The build carries no name of its
 * own (rnc-4 R15): with none given the mark draws nothing — `MFirmLogo` passes the host firm's.
 * `src` is the firm's uploaded logo (rnc-4 R27): a raster mark, so it takes `variant` and nothing else
 * (celer-09) — the picture replaces the words, named by `name` as its `alt`, and the text mark comes
 * back if the picture cannot load (a missing file, `storage:link` not run).
 */
const props = withDefaults(
  defineProps<{
    name?: string | null;
    src?: string | null;
    tone?: 'brand' | 'inherit';
    variant?: 'full' | 'min';
  }>(),
  {
    name: null,
    src: null,
    tone: 'brand',
    variant: 'full',
  }
);

const hasImageFailed = ref(false);

const fullName = computed(() => props.name?.trim() ?? '');
const firstWord = computed(() => fullName.value.split(' ')[0] ?? '');
const isImage = computed(() => Boolean(props.src) && !hasImageFailed.value);
const restOfName = computed(() => fullName.value.slice(firstWord.value.length).trim());

watch(() => props.src, onSourceChange);

// EVENTS
function onImageError() {
  hasImageFailed.value = true;
}

function onSourceChange() {
  hasImageFailed.value = false;
}
</script>

<template>
  <span
    v-if="fullName"
    :class="['inline-flex items-center leading-none', { 'text-brand-word': props.tone === 'brand' }]"
  >
    <img
      v-if="isImage"
      data-testid="logo-image"
      :alt="fullName"
      :class="['h-[1.75em] max-w-full object-contain', props.variant === 'min' ? 'aspect-square w-[1.75em]' : 'w-auto']"
      :src="props.src ?? undefined"
      @error="onImageError"
    />

    <span
      v-else-if="props.variant === 'min'"
      class="bg-primary-500 text-primary-contrast inline-flex aspect-square h-[1.75em] items-center justify-center rounded-md font-bold"
      role="img"
      :aria-label="fullName"
    >
      {{ firstWord.charAt(0) }}
    </span>

    <span
      v-else
      class="font-semibold tracking-tight whitespace-nowrap"
    >
      {{ firstWord }}
      <span
        v-if="restOfName"
        class="font-normal"
        >{{ restOfName }}</span
      >
    </span>
  </span>
</template>
