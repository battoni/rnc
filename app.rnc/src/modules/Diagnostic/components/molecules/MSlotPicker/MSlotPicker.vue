<script setup lang="ts">
import { computed, onMounted } from 'vue';
import type { BookingConfirmation } from '../../../interfaces';
import { useBooking, useMeetingTime } from '../../../composables';

/*
 * The slot picker (rnc-1 R15, R16, AM5): the specialists — the one indicated for the profile chosen
 * first, as the demo's `slotPicker()` — the next 10 working days with how many slots each has, the
 * day's 30-minute slots, the e-mail when the lead left only a phone, and the booking. A slot taken
 * meanwhile is "acabou de ser reservado" and the agenda is read again.
 */
const props = defineProps<{
  needsEmail: boolean;
  token: string;
}>();

const emit = defineEmits<{
  onBooked: [confirmation: BookingConfirmation];
}>();

const {
  availability,
  book,
  chooseDay,
  chooseSlot,
  chooseSpecialist,
  chosenDate,
  chosenSlot,
  days,
  email,
  errorKey,
  isBooking,
  load,
  slots,
  specialistId,
} = useBooking(() => props.token);
const { formatDay, formatMeeting, formatTime, formatWeekday } = useMeetingTime();

const slotMinutes = computed(() => availability.value?.slot_minutes ?? 30);

onMounted(load);

// HELPERS
function initialsOf(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('');
}

// EVENTS
function onConfirm() {
  book(props.needsEmail).then((confirmation) => {
    if (!confirmation) return;

    emit('onBooked', confirmation);
  });
}
</script>

<template>
  <section
    class="flex flex-col gap-6"
    data-testid="slot-picker"
  >
    <p class="text-body leading-relaxed">
      {{ $t('diagnostic.booking.intro', { minutes: slotMinutes }) }}
    </p>

    <div
      v-if="!availability"
      class="flex flex-col gap-3"
      :aria-label="$t('diagnostic.booking.loading')"
    >
      <Skeleton height="5rem" />

      <Skeleton height="4rem" />

      <Skeleton height="3rem" />
    </div>

    <template v-else>
      <div class="flex flex-col gap-2">
        <span class="text-muted text-sm">
          {{ $t('diagnostic.booking.specialists') }}
        </span>

        <div
          class="grid grid-cols-1 gap-3 sm:grid-cols-2"
          role="radiogroup"
          :aria-label="$t('diagnostic.booking.specialists')"
        >
          <button
            v-for="entry in availability.specialists"
            data-testid="specialist"
            :key="entry.id"
            role="radio"
            type="button"
            :aria-checked="entry.id === specialistId ? 'true' : 'false'"
            :class="[
              'touch-target flex min-w-0 items-center gap-3 rounded-lg border p-4 text-left transition-colors',
              entry.id === specialistId
                ? 'border-primary-500 bg-primary-50 ring-primary-500 ring-1'
                : 'border-line-strong hover:bg-panel-muted',
            ]"
            :data-id="entry.id"
            @click="chooseSpecialist(entry.id)"
          >
            <span
              aria-hidden="true"
              class="bg-primary-900 text-primary-100 flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-bold"
            >
              {{ initialsOf(entry.name) }}
            </span>

            <span class="flex min-w-0 flex-1 flex-col gap-1">
              <strong class="text-heading">{{ entry.name }}</strong>

              <span
                v-if="entry.area"
                class="text-muted text-sm"
              >
                {{ entry.area }}
              </span>

              <span
                v-if="entry.suggested"
                class="bg-primary-100 text-primary-950 inline-flex w-fit items-center gap-1 rounded-sm px-2 py-0.5 text-xs font-semibold"
                data-testid="specialist-suggested"
              >
                <i
                  aria-hidden="true"
                  class="pi pi-star-fill text-[0.65rem]"
                />

                {{ $t('diagnostic.booking.suggested') }}
              </span>
            </span>
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <span class="text-muted text-sm">
          {{ $t('diagnostic.booking.days') }}
        </span>

        <div
          class="grid grid-cols-3 gap-2 sm:grid-cols-5"
          role="group"
          :aria-label="$t('diagnostic.booking.days')"
        >
          <button
            v-for="day in days"
            data-testid="slot-day"
            :key="day.date"
            type="button"
            :aria-pressed="day.date === chosenDate ? 'true' : 'false'"
            :class="[
              'touch-target flex flex-col items-center gap-0.5 rounded-md border px-2 py-2.5 transition-colors disabled:cursor-not-allowed disabled:opacity-45',
              day.date === chosenDate
                ? 'border-primary-500 bg-primary-500 text-primary-contrast'
                : 'border-line-strong hover:bg-panel-muted',
            ]"
            :data-day="day.date"
            :data-free="day.slots.length"
            :disabled="day.slots.length === 0"
            @click="chooseDay(day.date)"
          >
            <span class="text-xs font-semibold tracking-wide uppercase">{{ formatWeekday(day.date) }}</span>

            <strong class="text-base tabular-nums">{{ formatDay(day.date) }}</strong>

            <span :class="['text-xs', day.date === chosenDate ? 'text-primary-contrast' : 'text-muted']">
              {{ $t('diagnostic.booking.slotCount', day.slots.length) }}
            </span>
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <span class="text-muted text-sm">
          {{
            chosenDate
              ? $t('diagnostic.booking.slotsOf', { day: formatDay(chosenDate) })
              : $t('diagnostic.booking.slots')
          }}
        </span>

        <div
          v-if="slots.length > 0"
          class="grid grid-cols-3 gap-2 sm:grid-cols-6"
          role="group"
          :aria-label="$t('diagnostic.booking.slots')"
        >
          <button
            v-for="slot in slots"
            data-testid="slot"
            :key="slot"
            type="button"
            :aria-pressed="slot === chosenSlot ? 'true' : 'false'"
            :class="[
              'touch-target rounded-md border px-2 py-2.5 text-sm font-semibold tabular-nums transition-colors',
              slot === chosenSlot
                ? 'border-primary-500 bg-primary-500 text-primary-contrast'
                : 'border-line-strong text-heading hover:bg-panel-muted',
            ]"
            :data-slot="slot"
            @click="chooseSlot(slot)"
          >
            {{ formatTime(slot) }}
          </button>
        </div>

        <p
          v-else
          class="text-muted text-sm"
        >
          {{ $t('diagnostic.booking.noSlots') }}
        </p>
      </div>

      <div
        v-if="needsEmail"
        class="flex flex-col gap-2"
      >
        <label
          class="text-muted mb-2 block text-sm"
          for="booking-email"
        >
          {{ $t('diagnostic.booking.email') }}
        </label>

        <InputText
          v-model="email"
          id="booking-email"
          autocomplete="email"
          data-testid="booking-email"
          inputmode="email"
          type="email"
          :invalid="errorKey === 'diagnostic.booking.errors.email'"
        />

        <small class="text-muted text-xs">
          {{ $t('diagnostic.booking.emailHint') }}
        </small>
      </div>
    </template>

    <p
      v-if="errorKey"
      class="text-danger-700 text-sm"
      data-testid="booking-error"
      role="alert"
    >
      {{ $t(errorKey) }}
    </p>

    <div class="flex">
      <Button
        class="max-w-full"
        data-testid="slot-confirm"
        icon="pi pi-check"
        :disabled="!chosenSlot"
        :label="
          chosenSlot
            ? $t('diagnostic.booking.confirm', { meeting: formatMeeting(chosenSlot, 'short') })
            : $t('diagnostic.booking.chooseSlot')
        "
        :loading="isBooking"
        @click="onConfirm"
      />
    </div>
  </section>
</template>
