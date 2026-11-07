<script setup lang="ts">
withDefaults(
  defineProps<{
    label?: string;
    wordmark?: string;
  }>(),
  {
    label: 'engineered by',
    wordmark: 'battoni.dev',
  }
);
</script>

<template>
  <div class="flex items-center gap-2.5">
    <ABrandMark class="brand-attribution-mark h-5 w-auto" />

    <div class="flex flex-col gap-0.5 leading-none">
      <span class="text-muted font-mono text-xs tracking-[0.18em] uppercase">{{ label }}</span>

      <span class="text-heading font-mono text-sm font-medium tracking-tight">{{ wordmark }}</span>
    </div>
  </div>
</template>
