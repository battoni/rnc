<script setup lang="ts">
import type { BookingConfirmation, DiagnosticResult } from '../../../interfaces';
import { RESULT_KIND } from '../../../enums';

/*
 * The result as the chat tells it (rnc-2 R12): the form's own result — the count, each item with
 * its kind, title and R$ range, always an estimate and locked until the meeting — in the reception's
 * bubble, then the same slot picker and, once booked, the same confirmation. The simulator stays
 * with the form: the link switches to it.
 */
const props = defineProps<{
  booking: BookingConfirmation | null;
  companyName: string | null;
  needsEmail: boolean;
  profileLine: string;
  result: DiagnosticResult;
  token: string | null;
}>();

const emit = defineEmits<{
  onBooked: [confirmation: BookingConfirmation];
  onSimulator: [];
}>();

// EVENTS
function onBooked(confirmation: BookingConfirmation) {
  emit('onBooked', confirmation);
}

function onSimulator() {
  emit('onSimulator');
}
</script>

<template>
  <div
    class="flex flex-col gap-3"
    data-testid="chat-result"
  >
    <div
      class="motion-bubble bg-panel-muted text-body flex w-full max-w-full flex-col gap-3 self-start rounded-xl rounded-bl-md px-4 py-3 sm:max-w-[92%]"
    >
      <span class="text-muted text-xs font-semibold">{{ $t('diagnostic.chat.who') }}</span>

      <strong
        class="text-heading text-base font-semibold text-balance"
        data-testid="result-summary"
      >
        {{
          $t('diagnostic.result.summary', {
            opportunities: $t('diagnostic.result.opportunities', props.result.opportunities),
            risks: $t('diagnostic.result.risks', props.result.risks),
          })
        }}
      </strong>

      <ul class="flex flex-col gap-2">
        <li
          v-for="(item, index) in props.result.items"
          class="motion-bubble bg-panel flex flex-col gap-1.5 rounded-lg p-3"
          data-testid="result-item"
          :key="item.title"
          :data-kind="item.kind"
          :style="{ '--reveal-index': index + 1 }"
        >
          <span class="flex flex-wrap items-center gap-2">
            <span
              :class="[
                'w-fit shrink-0 rounded-sm px-2 py-0.5 text-xs font-semibold',
                item.kind === RESULT_KIND.OPPORTUNITY ? 'bg-success-100 text-success-800' : 'bg-warn-100 text-warn-800',
              ]"
            >
              {{
                item.kind === RESULT_KIND.OPPORTUNITY
                  ? $t('diagnostic.result.opportunity')
                  : $t('diagnostic.result.risk')
              }}
            </span>

            <strong class="text-heading text-sm">{{ item.title }}</strong>
          </span>

          <span class="text-heading text-sm font-semibold tabular-nums">
            {{ item.range_label }}
            <span class="text-muted text-xs font-normal">· {{ $t('diagnostic.result.estimate') }}</span>
          </span>

          <span
            class="text-muted flex items-center gap-1.5 text-xs"
            data-testid="locked"
          >
            <i
              aria-hidden="true"
              class="pi pi-lock text-subtle text-xs"
            />

            {{ $t('diagnostic.result.locked') }}
          </span>
        </li>
      </ul>

      <p class="text-muted text-xs">{{ $t('diagnostic.result.profile', { profile: props.profileLine }) }}</p>

      <button
        class="touch-target text-heading hover:text-body w-fit text-left text-sm font-semibold underline underline-offset-4"
        data-testid="chat-simulator"
        type="button"
        @click="onSimulator"
      >
        {{ $t('diagnostic.chat.result.simulator') }}
      </button>
    </div>

    <MBookingConfirmation
      v-if="props.booking"
      class="motion-bubble"
      :booking="props.booking"
      :company-name="props.companyName"
      :result="props.result"
    />

    <template v-else-if="props.token">
      <p
        class="motion-bubble bg-panel-muted text-body max-w-[85%] self-start rounded-xl rounded-bl-md px-4 py-3 text-sm leading-relaxed"
        :style="{ '--reveal-index': props.result.items.length + 1 }"
      >
        {{ $t('diagnostic.chat.result.specialist') }}
      </p>

      <MSlotPicker
        :needs-email="props.needsEmail"
        :token="props.token"
        @on-booked="onBooked"
      />
    </template>
  </div>
</template>
