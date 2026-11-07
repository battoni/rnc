<script setup lang="ts">
import { computed } from 'vue';
import { useMediaQuery } from '@vueuse/core';

const props = withDefaults(
  defineProps<{
    icon: string;
    isQuiet?: boolean;
    label: string;
    variant?: 'primary' | 'soft-primary';
  }>(),
  {
    isQuiet: false,
    variant: 'soft-primary',
  }
);

const isMdAndUp = useMediaQuery('(min-width: 768px)');

const variantClass = computed(() => {
  if (props.variant === 'primary') {
    return 'celer-button-primary shadow-none';
  }

  if (props.isQuiet) {
    return 'celer-button-soft-primary shadow-none';
  }

  return 'celer-button-soft-primary shadow transition-all motion-calm active:scale-95';
});

const buttonClass = computed(() => [
  variantClass.value,
  'max-md:size-11 max-md:justify-center max-md:p-0 shrink-0 md:w-fit',
]);

const visibleLabel = computed(() => (isMdAndUp.value ? props.label : undefined));
</script>

<template>
  <Button
    :aria-label="label"
    :class="buttonClass"
    :icon
    :label="visibleLabel"
    v-bind="$attrs"
  />
</template>
