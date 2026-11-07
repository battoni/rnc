<script setup lang="ts">
/**
 * The attention dot: something behind this entry has not been seen.
 *
 * A dot and not a number, on purpose. A count reads as a queue to clear; this marks something
 * worth reading, and "3" would make reading it feel like housework.
 *
 * It pulses because a static dot in a rail of eleven items is invisible — but the pulse is a slow
 * breath rather than a blink, and it stops entirely under `prefers-reduced-motion`, where the dot
 * alone still carries the whole message.
 */
defineProps<{ label?: string }>();
</script>

<template>
  <span
    class="relative inline-flex size-2 shrink-0"
    role="status"
    :aria-label="label"
  >
    <span class="bg-primary-500 nav-dot-halo absolute inline-flex size-full rounded-full opacity-60" />

    <span class="bg-primary-500 relative inline-flex size-2 rounded-full" />
  </span>
</template>

<style scoped>
.nav-dot-halo {
  animation: nav-dot-breath 2.4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes nav-dot-breath {
  0% {
    opacity: 0.6;
    transform: scale(1);
  }

  70%,
  100% {
    opacity: 0;
    transform: scale(2.4);
  }
}

/* The dot alone still says everything; the movement is the part that can be unwelcome. */
@media (prefers-reduced-motion: reduce) {
  .nav-dot-halo {
    animation: none;
  }
}
</style>
