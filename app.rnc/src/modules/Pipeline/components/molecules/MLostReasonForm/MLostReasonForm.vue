<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LeadMove, PendingLostMove } from '../../../interfaces';
import { translateError } from '@Helpers';
import { LOST_REASON_MAX_LENGTH } from '../../../constants';
import { LEAD_STAGE } from '../../../enums';
import { MoveLeadStageService } from '../../../services';

/*
 * Perdido needs a reason (rnc-1 R18): the form the board and the lead detail open in their
 * `MMainDialog` before the move. It owns the call and shows the API's refusal inline; the parent
 * applies the answer (celer-07, "A destructive action that needs input is a form").
 */
interface MoveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [move: LeadMove];
}>();

const props = defineProps<{
  pending: PendingLostMove;
}>();

const error = ref<string | null>(null);
const isSaving = ref(false);
const reason = ref('');

const trimmedReason = computed(() => reason.value.trim());

// EVENTS
function onSubmit() {
  if (!trimmedReason.value) {
    error.value = translateError('pipeline.lostReasonRequired');
    return;
  }

  const payload = { reason: trimmedReason.value, stage: LEAD_STAGE.PERDIDO, via: props.pending.via };

  isSaving.value = true;
  error.value = null;

  MoveLeadStageService(props.pending.id, payload)
    .then((response) => emit('onSuccess', response.data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: MoveError) {
  const firstError = Object.values(failure.response?.data?.errors ?? {})[0]?.[0];

  error.value = firstError
    ? translateError(firstError)
    : (failure.translatedMessage ?? translateError('errors.unexpected'));
}
</script>

<template>
  <form
    novalidate
    id="lost-reason-form"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    data-testid="lost-reason-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body text-sm">
      {{ $t('pipeline.lost.lede', { company: props.pending.company ?? $t('pipeline.anonymous') }) }}
    </p>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="lost-reason"
      >
        {{ $t('pipeline.lost.reasonLabel') }}
      </label>

      <Textarea
        v-model="reason"
        autofocus
        autoResize
        id="lost-reason"
        class="w-full"
        data-testid="lost-reason"
        rows="3"
        :invalid="!!error"
        :maxlength="LOST_REASON_MAX_LENGTH"
        :placeholder="$t('pipeline.lost.reasonPlaceholder')"
      />
    </div>

    <AFormError :error />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('pipeline.lost.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="lost-confirm"
      form="lost-reason-form"
      severity="danger"
      type="submit"
      :label="$t('pipeline.lost.confirm')"
      :loading="isSaving"
    />
  </div>
</template>
