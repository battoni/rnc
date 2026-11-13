<script setup lang="ts">
import { ref, useId } from 'vue';
import type { AssistantPerson, AssistantQuestion } from '../../../interfaces';
import { translateError } from '@Helpers';
import { HandOffQuestionService } from '../../../services';

/*
 * No served note answered (rnc-3 R13, C4): the demo's fixed sentence — the screen's, never the API's —
 * and "Encaminhar ao especialista" with a picker of the firm's active specialists, the first by name
 * chosen (W11). Handing it over makes one question and rings that specialist's bell; then the card
 * says who has it. It owns the call (celer-07) and tells the view the question it made.
 */
interface HandOffError {
  translatedMessage?: string;
}

const emit = defineEmits<{
  onHanded: [question: AssistantQuestion];
}>();

const props = defineProps<{
  question: string;
  specialists: AssistantPerson[];
}>();

const fieldId = useId();

const error = ref<string | null>(null);
const handedTo = ref<AssistantPerson | null>(null);
const isSending = ref(false);
const specialistId = ref<number | null>(props.specialists[0]?.id ?? null);

// EVENTS
function onHandOff() {
  if (specialistId.value === null || isSending.value) return;

  error.value = null;
  isSending.value = true;

  HandOffQuestionService(props.question, specialistId.value)
    .then(({ data }) => {
      handedTo.value = data.specialist;
      emit('onHanded', data);
    })
    .catch(
      (failure: HandOffError) => (error.value = failure.translatedMessage ?? translateError('assistant.handoff.failed'))
    )
    .finally(() => (isSending.value = false));
}
</script>

<template>
  <div
    class="flex min-w-0 flex-col gap-3"
    data-testid="handoff-card"
  >
    <p class="text-body leading-relaxed">{{ $t('assistant.handoff.noNote') }}</p>

    <p
      v-if="handedTo"
      class="motion-enter bg-success-50 text-success-800 flex items-start gap-2 rounded-md px-3 py-2 text-sm font-medium"
      data-testid="handoff-done"
      role="status"
    >
      <i
        aria-hidden="true"
        class="pi pi-check-circle mt-0.5 text-sm"
      />
      <span>{{ $t('assistant.handoff.done', { name: handedTo.name }) }}</span>
    </p>

    <p
      v-else-if="props.specialists.length === 0"
      class="text-muted text-sm"
    >
      {{ $t('assistant.handoff.noSpecialist') }}
    </p>

    <div
      v-else
      class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end"
    >
      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          :for="fieldId"
        >
          {{ $t('assistant.handoff.picker') }}
        </label>

        <Select
          v-model="specialistId"
          fluid
          data-testid="handoff-specialist"
          optionLabel="name"
          optionValue="id"
          :inputId="fieldId"
          :options="props.specialists"
        />
      </div>

      <Button
        data-testid="handoff-send"
        icon="pi pi-send"
        :label="$t('assistant.handoff.send')"
        :loading="isSending"
        @click="onHandOff"
      />
    </div>

    <AFormError :error />
  </div>
</template>
