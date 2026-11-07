<script setup lang="ts">
/**
 * `icon` carries the empty state on its own, and it has a default so that it always can.
 *
 * The fallback used to render `<ABrandMark>` — the bootstrap author's own mark, whose accessible name is a
 * person's — so every icon-less empty state in every product generated from this bootstrap
 * announced that name to a screen reader. It was found in a QA weeks after it existed, which is
 * how a wrong default usually surfaces: nothing fails, it just quietly says the wrong thing.
 *
 * There is a second reason, structural rather than editorial. This component is `MERGE` in the
 * sync manifest and travels to every project; `ABrandMark` is `LOCAL` and never leaves. A shared
 * component reaching into the brand is a dependency pointing the wrong way, and the fallback was
 * the only place it happened.
 */
withDefaults(
  defineProps<{
    description?: string;
    glyph?: string;
    icon?: string;
    title?: string;
  }>(),
  {
    glyph: '>_',
    icon: 'pi pi-inbox',
  }
);
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-5 px-6 py-16 text-center">
    <div class="brand-corners border-line bg-panel flex size-16 items-center justify-center rounded-lg border">
      <i :class="[icon, 'text-subtle text-2xl']" />
    </div>

    <div class="flex flex-col items-center gap-1.5">
      <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">{{ glyph }}</span>

      <h3
        v-if="title"
        class="text-heading text-lg font-semibold"
      >
        {{ title }}
      </h3>

      <p
        v-if="description"
        class="text-muted max-w-sm text-sm"
      >
        {{ description }}
      </p>
    </div>

    <div v-if="$slots.action">
      <slot name="action" />
    </div>
  </div>
</template>
