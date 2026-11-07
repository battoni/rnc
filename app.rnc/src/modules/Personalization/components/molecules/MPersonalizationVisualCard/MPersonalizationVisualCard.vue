<script setup lang="ts">
import { computed, useId } from 'vue';
import { storeToRefs } from 'pinia';
import { useLocale } from '@Composables';
import { COLOR_SCHEME } from '@Enums';
import useUiStore, { useColorSchemeStore } from '@Stores';

const colorSchemeStore = useColorSchemeStore();
const uiStore = useUiStore();
const { isFullscreen, isMenuButtonPinned, isTutorEnabled } = storeToRefs(uiStore);
const { isDark } = storeToRefs(colorSchemeStore);

const { availableLocales, currentLocale, setLocale } = useLocale();

const cardId = useId();

const darkModeInputId = `${cardId}-dark-mode`;
const languageInputId = `${cardId}-language`;
const fullWidthInputId = `${cardId}-full-width`;
const pinMenuInputId = `${cardId}-pin-menu`;
const tutorInputId = `${cardId}-tutor`;

const isDarkMode = computed({
  get: () => isDark.value,
  set: (value: boolean) => colorSchemeStore.setPreference(value ? COLOR_SCHEME.DARK : COLOR_SCHEME.LIGHT),
});

/** The language belongs with the other preferences: this card is the one surface for them. */
const language = computed({
  get: () => currentLocale.value,
  set: (value) => setLocale(value),
});

const isFullWidth = computed({
  get: () => isFullscreen.value,
  set: (value: boolean) => uiStore.setFullscreen(value),
});

/**
 * Pinning keeps the menu button on screen at every width, which turns the navigation rail into
 * something you fold away and bring back. The setter goes through `toggleMenuButtonPinned` rather
 * than assigning, because unpinning also has to restore the rail — the button is the only way to
 * reopen it, so leaving it hidden without the button would strand the navigation off screen.
 */
const isMenuPinned = computed({
  get: () => isMenuButtonPinned.value,
  set: (value: boolean) => {
    if (value !== isMenuButtonPinned.value) uiStore.toggleMenuButtonPinned();
  },
});

const isTutorOn = computed({
  get: () => isTutorEnabled.value,
  set: (value: boolean) => uiStore.setTutorEnabled(value),
});
</script>

<template>
  <section
    class="bg-panel shadow-lift flex flex-col gap-5 rounded-lg p-5"
    data-testid="personalization-visual-card"
  >
    <div class="flex items-start gap-3">
      <span
        class="bg-primary-100 text-primary-950 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
        data-testid="personalization-visual-card-icon"
      >
        <i class="pi pi-palette text-lg" />
      </span>

      <h2 class="text-heading pt-1.5 text-base font-semibold">
        {{ $t('personalization.visualCard.title') }}
      </h2>
    </div>

    <div class="flex flex-col gap-4">
      <div class="flex items-center justify-between gap-4">
        <label
          class="text-muted flex min-w-0 items-center gap-3 text-sm"
          :for="darkModeInputId"
        >
          <span class="pi pi-moon text-heading text-base" />

          <span>{{ $t('personalization.visualCard.darkMode') }}</span>
        </label>

        <ToggleSwitch
          v-model="isDarkMode"
          :aria-label="$t('personalization.visualCard.darkMode')"
          :inputId="darkModeInputId"
        />
      </div>

      <div class="flex items-center justify-between gap-4">
        <label
          class="text-muted flex min-w-0 items-center gap-3 text-sm"
          :for="languageInputId"
        >
          <span class="pi pi-globe text-heading text-base" />

          <span>{{ $t('personalization.visualCard.language') }}</span>
        </label>

        <Select
          v-model="language"
          class="w-40"
          optionLabel="label"
          optionValue="value"
          :aria-label="$t('personalization.visualCard.language')"
          :inputId="languageInputId"
          :options="availableLocales"
        />
      </div>

      <div class="flex items-center justify-between gap-4">
        <label
          class="text-muted flex min-w-0 items-center gap-3 text-sm"
          :for="fullWidthInputId"
        >
          <span class="pi pi-expand text-heading text-base" />

          <span>{{ $t('personalization.visualCard.fullWidth') }}</span>
        </label>

        <ToggleSwitch
          v-model="isFullWidth"
          :aria-label="$t('personalization.visualCard.fullWidth')"
          :inputId="fullWidthInputId"
        />
      </div>

      <div class="flex items-center justify-between gap-4">
        <label
          class="text-muted flex min-w-0 items-center gap-3 text-sm"
          :for="pinMenuInputId"
        >
          <span class="pi pi-bars text-heading text-base" />

          <span>{{ $t('personalization.visualCard.pinMenuButton') }}</span>
        </label>

        <ToggleSwitch
          v-model="isMenuPinned"
          :aria-label="$t('personalization.visualCard.pinMenuButton')"
          :inputId="pinMenuInputId"
        />
      </div>

      <div class="flex items-center justify-between gap-4">
        <label
          class="text-muted flex min-w-0 items-center gap-3 text-sm"
          :for="tutorInputId"
        >
          <span class="pi pi-lightbulb text-heading text-base" />

          <span>{{ $t('personalization.visualCard.tutor') }}</span>
        </label>

        <ToggleSwitch
          v-model="isTutorOn"
          :aria-label="$t('personalization.visualCard.tutor')"
          :inputId="tutorInputId"
        />
      </div>
    </div>
  </section>
</template>
