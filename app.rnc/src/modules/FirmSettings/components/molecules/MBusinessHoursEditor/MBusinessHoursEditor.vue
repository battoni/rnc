<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PublicFirmHours } from '@Interfaces';
import { FIRM_NEW_WINDOW, FIRM_SETTINGS_LIMITS, FIRM_WEEKDAYS } from '../../../constants';

/*
 * The firm's business hours (rnc-4 R30): windows of weekdays sharing one opening and closing time,
 * as `firm/settings` stores them ("Mon–Thu 08:00–18:00", "Fri 08:00–17:00"). Each weekday is a toggle
 * with its own name read aloud; the parent validates and passes one message per window back in.
 */
const props = defineProps<{
  errors?: Record<number, string>;
}>();

const { locale } = useI18n();

const windows = defineModel<PublicFirmHours[]>({ required: true });

const canAdd = computed(() => windows.value.length < FIRM_SETTINGS_LIMITS.firmWindows);

const weekdayNames = computed(() => {
  const format = new Intl.DateTimeFormat(locale.value, { weekday: 'short' });
  const longFormat = new Intl.DateTimeFormat(locale.value, { weekday: 'long' });

  // 2024-01-01 was a Monday: day n of that week is ISO weekday n.
  return FIRM_WEEKDAYS.map((weekday) => ({
    long: longFormat.format(new Date(2024, 0, weekday)),
    short: format.format(new Date(2024, 0, weekday)),
    weekday,
  }));
});

// HELPERS
function isOn(span: PublicFirmHours, weekday: number): boolean {
  return span.weekdays.includes(weekday);
}

function replaceWindow(index: number, span: PublicFirmHours) {
  windows.value = windows.value.map((current, position) => (position === index ? span : current));
}

// EVENTS
function onToggle(index: number, weekday: number) {
  const span = windows.value[index];

  if (!span) return;

  const weekdays = isOn(span, weekday)
    ? span.weekdays.filter((current) => current !== weekday)
    : [...span.weekdays, weekday].sort((first, second) => first - second);

  replaceWindow(index, { ...span, weekdays });
}

function onTime(index: number, field: 'from' | 'to', value: string | undefined) {
  const span = windows.value[index];

  if (!span) return;

  replaceWindow(index, { ...span, [field]: value ?? '' });
}

function onAdd() {
  windows.value = [...windows.value, { ...FIRM_NEW_WINDOW, weekdays: [] }];
}

function onRemove(index: number) {
  windows.value = windows.value.filter((_span, position) => position !== index);
}
</script>

<template>
  <div class="flex min-w-0 flex-col gap-3">
    <fieldset
      v-for="(span, index) in windows"
      class="border-line flex min-w-0 flex-col gap-3 rounded-lg border p-3"
      data-testid="hours-window"
      :key="index"
    >
      <legend class="text-muted px-1 text-sm">{{ $t('firmSettings.hours.window', { n: index + 1 }) }}</legend>

      <div
        class="flex flex-wrap gap-3"
        role="group"
        :aria-label="$t('firmSettings.hours.weekdays')"
      >
        <button
          v-for="day in weekdayNames"
          :key="day.weekday"
          type="button"
          :aria-label="day.long"
          :aria-pressed="isOn(span, day.weekday)"
          :class="[
            'touch-target min-w-12 rounded-md border px-2 py-1.5 text-sm font-semibold capitalize',
            isOn(span, day.weekday)
              ? 'bg-primary-500 border-primary-500 text-primary-contrast'
              : 'border-line-strong text-body bg-panel',
          ]"
          :data-testid="`hours-weekday-${index}-${day.weekday}`"
          @click="onToggle(index, day.weekday)"
        >
          {{ day.short }}
        </button>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            :for="`hours-from-${index}`"
          >
            {{ $t('firmSettings.hours.from') }}
          </label>

          <InputText
            :id="`hours-from-${index}`"
            class="w-32"
            type="time"
            :data-testid="`hours-from-${index}`"
            :invalid="!!props.errors?.[index]"
            :modelValue="span.from"
            @update:modelValue="onTime(index, 'from', $event)"
          />
        </div>

        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            :for="`hours-to-${index}`"
          >
            {{ $t('firmSettings.hours.to') }}
          </label>

          <InputText
            :id="`hours-to-${index}`"
            class="w-32"
            type="time"
            :data-testid="`hours-to-${index}`"
            :invalid="!!props.errors?.[index]"
            :modelValue="span.to"
            @update:modelValue="onTime(index, 'to', $event)"
          />
        </div>

        <Button
          icon="pi pi-trash"
          severity="secondary"
          size="small"
          :aria-label="$t('firmSettings.hours.remove', { n: index + 1 })"
          :data-testid="`hours-remove-${index}`"
          @click="onRemove(index)"
        />
      </div>

      <AFormError
        :data-testid="`hours-error-${index}`"
        :error="props.errors?.[index]"
      />
    </fieldset>

    <p
      v-if="windows.length === 0"
      class="text-muted text-sm"
    >
      {{ $t('firmSettings.hours.none') }}
    </p>

    <div>
      <Button
        v-if="canAdd"
        data-testid="hours-add"
        icon="pi pi-plus"
        severity="secondary"
        size="small"
        :label="$t('firmSettings.hours.add')"
        @click="onAdd"
      />
    </div>
  </div>
</template>
