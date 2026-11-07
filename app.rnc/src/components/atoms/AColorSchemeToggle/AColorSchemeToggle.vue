<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useColorSchemeStore } from '@Stores';

const colorSchemeStore = useColorSchemeStore();

const { t } = useI18n();

const icon = computed(() => (colorSchemeStore.isDark ? 'pi pi-sun' : 'pi pi-moon'));
const label = computed(() => (colorSchemeStore.isDark ? t('theme.switchToLight') : t('theme.switchToDark')));

// EVENTS
function onToggle() {
  colorSchemeStore.toggle();
}
</script>

<template>
  <Button
    plain
    text
    unstyled
    class="hover:bg-primary-100 hover:text-primary-950 motion-calm flex w-full cursor-pointer items-center gap-2 rounded-sm p-1 px-2 transition-colors"
    type="button"
    @click="onToggle"
  >
    <span :class="['rounded-sm p-2 text-xl', icon]" />

    <span>{{ label }}</span>
  </Button>
</template>
