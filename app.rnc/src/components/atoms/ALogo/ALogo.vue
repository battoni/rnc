<script setup lang="ts">
import { computed } from 'vue';
import LogoFullDark from './logo-full-dark.png';
import LogoFullLight from './logo-full-light.png';
import LogoMinDark from './logo-min-dark.png';
import LogoMinLight from './logo-min-light.png';

const props = withDefaults(
  defineProps<{
    variant?: 'full' | 'min';
  }>(),
  {
    variant: 'full',
  }
);

const darkSrc = computed(() => (props.variant === 'full' ? LogoFullDark : LogoMinDark));
const lightSrc = computed(() => (props.variant === 'full' ? LogoFullLight : LogoMinLight));
</script>

<template>
  <div class="flex">
    <img
      alt="app.rnc Logo"
      class="w-full dark:hidden"
      :src="lightSrc"
    />

    <img
      alt="app.rnc Logo"
      class="hidden w-full dark:block"
      :src="darkSrc"
    />
  </div>
</template>
