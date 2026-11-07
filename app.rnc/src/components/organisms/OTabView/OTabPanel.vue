<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router';

defineOptions({ name: 'OTabPanel' });

defineProps<{
  icon?: string;
  tipKey?: string;
  title: string;
  to?: RouteLocationRaw;
  value?: string;
}>();
</script>

<template>
  <slot />
</template>
