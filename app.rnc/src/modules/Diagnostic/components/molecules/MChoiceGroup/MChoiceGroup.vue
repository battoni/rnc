<script setup lang="ts">
import type { ChoiceOption } from '../../../interfaces';

/*
 * One question of the quiz as a group of large, tappable answers (rnc-1 R10) — a radio group drawn as
 * buttons, so a phone gets 44 px targets and the chosen answer reads at a glance. Each answer has
 * `data-testid="{name}-{value}"`.
 */
const props = withDefaults(
  defineProps<{
    columnsClass?: string;
    label: string;
    name: string;
    options: ChoiceOption[];
  }>(),
  { columnsClass: 'grid-cols-2 sm:grid-cols-3' }
);

const model = defineModel<number | string | null>({ required: true });

// EVENTS
function onChoose(value: number | string) {
  model.value = value;
}
</script>

<template>
  <fieldset class="flex min-w-0 flex-col gap-2">
    <legend class="text-muted mb-2 block text-sm">
      {{ $t(props.label) }}
    </legend>

    <div
      role="radiogroup"
      :aria-label="$t(props.label)"
      :class="['grid gap-3', props.columnsClass]"
    >
      <button
        v-for="option in props.options"
        :key="option.value"
        role="radio"
        type="button"
        :aria-checked="model === option.value ? 'true' : 'false'"
        :class="[
          'touch-target flex min-h-11 items-center justify-center rounded-lg border px-3 py-2.5 text-center text-sm font-semibold transition-colors',
          model === option.value
            ? 'border-primary-500 bg-primary-500 text-primary-contrast'
            : 'border-line-strong bg-panel text-body hover:bg-panel-muted',
        ]"
        :data-testid="`${props.name}-${option.value}`"
        @click="onChoose(option.value)"
      >
        {{ $t(option.label, { value: option.value }) }}
      </button>
    </div>
  </fieldset>
</template>
