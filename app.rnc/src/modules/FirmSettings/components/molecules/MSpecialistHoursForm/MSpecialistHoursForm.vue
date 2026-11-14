<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SaveFailure, Specialist, SpecialistHour, SpecialistHoursPayload } from '../../../interfaces';
import { useApiRefusal } from '../../../composables';
import { FIRM_NEW_SPECIALIST_HOUR, FIRM_SETTINGS_LIMITS, FIRM_WEEKDAYS } from '../../../constants';
import { UpdateSpecialistHoursService } from '../../../services';

/*
 * One specialist's weekly hours (rnc-4 R32): one row per window — a weekday, an opening and a closing
 * time — sent as the whole week (`PUT specialists/{id}/hours`); an empty week closes the agenda. The
 * answer is the stored week in reading order and goes to the view for its row.
 */
const emit = defineEmits<{
  onClose: [];
  onSuccess: [hours: SpecialistHour[]];
}>();

const props = defineProps<{
  specialist: Specialist;
}>();

const TIME_PATTERN = /^\d{2}:\d{2}$/;

const { fieldErrorsOf, formErrorOf } = useApiRefusal();
const { locale, t } = useI18n();

const errors = ref<Record<number, string>>({});
const formError = ref<string | null>(null);
const hours = ref<SpecialistHour[]>(props.specialist.hours.map((hour) => ({ ...hour })));
const isSaving = ref(false);

const canAdd = computed(() => hours.value.length < FIRM_SETTINGS_LIMITS.specialistWindows);

const weekdayOptions = computed(() => {
  const format = new Intl.DateTimeFormat(locale.value, { weekday: 'long' });

  // 2024-01-01 was a Monday: day n of that week is ISO weekday n.
  return FIRM_WEEKDAYS.map((weekday) => ({ label: format.format(new Date(2024, 0, weekday)), weekday }));
});

// HELPERS
function rowError(hour: SpecialistHour): string | null {
  const hasTimes = TIME_PATTERN.test(hour.opens_at) && TIME_PATTERN.test(hour.closes_at);

  if (!hasTimes) return t('firmSettings.hours.timeRequired');

  return hour.closes_at > hour.opens_at ? null : t('firmSettings.hours.closesAfterOpens');
}

function validate(): Record<number, string> {
  return Object.fromEntries(
    hours.value
      .map((hour, index) => [index, rowError(hour)] as const)
      .filter((entry): entry is readonly [number, string] => entry[1] !== null)
  );
}

function rowErrorsOf(fieldErrors: Record<string, string>): Record<number, string> {
  return Object.fromEntries(
    Object.entries(fieldErrors)
      .map(([field, message]) => [Number(/^hours\.(\d+)\./.exec(field)?.[1]), message] as const)
      .filter(([index]) => !Number.isNaN(index))
  );
}

function replaceRow(index: number, hour: SpecialistHour) {
  hours.value = hours.value.map((current, position) => (position === index ? hour : current));
}

// EVENTS
function onWeekday(index: number, event: Event) {
  const hour = hours.value[index];

  if (!hour) return;

  replaceRow(index, { ...hour, weekday: Number((event.target as HTMLSelectElement).value) });
}

function onTime(index: number, field: 'closes_at' | 'opens_at', value: string | undefined) {
  const hour = hours.value[index];

  if (!hour) return;

  replaceRow(index, { ...hour, [field]: value ?? '' });
}

function onAdd() {
  hours.value = [...hours.value, { ...FIRM_NEW_SPECIALIST_HOUR }];
}

function onRemove(index: number) {
  hours.value = hours.value.filter((_hour, position) => position !== index);
  errors.value = {};
}

function onSubmit() {
  formError.value = null;
  errors.value = validate();

  if (Object.keys(errors.value).length > 0) return;

  const PAYLOAD: SpecialistHoursPayload = { hours: hours.value };

  isSaving.value = true;

  UpdateSpecialistHoursService(props.specialist.id, PAYLOAD)
    .then(({ data }) => emit('onSuccess', data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: SaveFailure) {
  const rowErrors = rowErrorsOf(fieldErrorsOf(failure));

  if (Object.keys(rowErrors).length === 0) {
    formError.value = formErrorOf(failure);
    return;
  }

  errors.value = rowErrors;
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[560px] flex-col gap-4">
    <form
      novalidate
      id="specialist-hours-form"
      class="flex flex-col gap-3"
      data-testid="hours-form"
      @submit.prevent="onSubmit"
    >
      <p class="text-body text-sm">{{ $t('firmSettings.specialists.formLede', { name: specialist.name }) }}</p>

      <fieldset
        v-for="(hour, index) in hours"
        class="border-line flex min-w-0 flex-col gap-2 rounded-lg border p-3"
        :key="index"
      >
        <legend class="sr-only">{{ $t('firmSettings.hours.window', { n: index + 1 }) }}</legend>

        <div class="flex flex-wrap items-end gap-3">
          <div class="flex min-w-36 flex-1 flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              :for="`hour-weekday-${index}`"
            >
              {{ $t('firmSettings.hours.weekday') }}
            </label>

            <select
              :id="`hour-weekday-${index}`"
              class="p-inputtext w-full capitalize"
              :data-testid="`hour-weekday-${index}`"
              :value="hour.weekday"
              @change="onWeekday(index, $event)"
            >
              <option
                v-for="option in weekdayOptions"
                :key="option.weekday"
                :value="option.weekday"
              >
                {{ option.label }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              :for="`hour-opens-${index}`"
            >
              {{ $t('firmSettings.hours.from') }}
            </label>

            <InputText
              :id="`hour-opens-${index}`"
              class="w-28"
              type="time"
              :data-testid="`hour-opens-${index}`"
              :invalid="!!errors[index]"
              :modelValue="hour.opens_at"
              @update:modelValue="onTime(index, 'opens_at', $event)"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              :for="`hour-closes-${index}`"
            >
              {{ $t('firmSettings.hours.to') }}
            </label>

            <InputText
              :id="`hour-closes-${index}`"
              class="w-28"
              type="time"
              :data-testid="`hour-closes-${index}`"
              :invalid="!!errors[index]"
              :modelValue="hour.closes_at"
              @update:modelValue="onTime(index, 'closes_at', $event)"
            />
          </div>

          <Button
            icon="pi pi-trash"
            severity="secondary"
            size="small"
            :aria-label="$t('firmSettings.hours.remove', { n: index + 1 })"
            @click="onRemove(index)"
          />
        </div>

        <AFormError
          :data-testid="`hour-error-${index}`"
          :error="errors[index]"
        />
      </fieldset>

      <p
        v-if="hours.length === 0"
        class="text-muted text-sm"
      >
        {{ $t('firmSettings.specialists.noHoursNote') }}
      </p>

      <div>
        <Button
          v-if="canAdd"
          data-testid="hour-add"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          :label="$t('firmSettings.hours.add')"
          @click="onAdd"
        />
      </div>

      <AFormError :error="formError" />
    </form>
  </div>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[560px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('firmSettings.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="hours-save"
      form="specialist-hours-form"
      type="submit"
      :label="$t('firmSettings.specialists.save')"
      :loading="isSaving"
    />
  </div>
</template>
