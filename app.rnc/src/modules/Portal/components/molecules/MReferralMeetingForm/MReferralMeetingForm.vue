<script setup lang="ts">
import { ref } from 'vue';
import type { PortalReferral } from '../../../interfaces';
import { translateError } from '@Helpers';
import { TRANSITION_ERROR_KEYS } from '../../../constants';
import { REFERRAL_STATUS } from '../../../enums';
import { TransitionReferralService } from '../../../services';

/*
 * "Agendar reunião" (rnc-2 R7): the meeting's date and time — in the future — before the move to
 * `reuniao`, in the inbox's `MMainDialog`. A native date-time field (W29): the partner types the
 * time on its own clock and the API gets the instant (ISO-8601, UTC). The form owns the call and
 * shows the API's refusal inline; the inbox applies the answer (celer-07, form molecule contract).
 */
interface MoveError {
  response?: { data?: { code?: string; errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [referral: PortalReferral];
}>();

const props = defineProps<{
  referral: PortalReferral;
}>();

const error = ref<string | null>(null);
const isSaving = ref(false);
const meetingAt = ref('');

// EVENTS
function onSubmit() {
  const at = meetingAt.value ? new Date(meetingAt.value) : null;

  if (!at || Number.isNaN(at.getTime())) {
    error.value = translateError('portal.referrals.meetingRequired');
    return;
  }

  if (at.getTime() <= Date.now()) {
    error.value = translateError('portal.referrals.meetingInPast');
    return;
  }

  isSaving.value = true;
  error.value = null;

  TransitionReferralService(props.referral.id, { meeting_at: at.toISOString(), to: REFERRAL_STATUS.REUNIAO })
    .then((response) => emit('onSuccess', response.data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: MoveError) {
  const data = failure.response?.data;
  const firstError = TRANSITION_ERROR_KEYS[data?.code ?? ''] ?? Object.values(data?.errors ?? {})[0]?.[0];

  error.value = firstError
    ? translateError(firstError)
    : (failure.translatedMessage ?? translateError('errors.unexpected'));
}
</script>

<template>
  <form
    novalidate
    id="meeting-form"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    data-testid="meeting-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body text-sm">
      {{ $t('portal.meeting.lede', { company: props.referral.company ?? $t('pipeline.anonymous') }) }}
    </p>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="meeting-at"
      >
        {{ $t('portal.meeting.label') }}
      </label>

      <InputText
        v-model="meetingAt"
        autofocus
        id="meeting-at"
        class="w-full"
        data-testid="meeting-at"
        type="datetime-local"
        :invalid="!!error"
      />
    </div>

    <AFormError :error />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('portal.meeting.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="meeting-confirm"
      form="meeting-form"
      type="submit"
      :label="$t('portal.meeting.confirm')"
      :loading="isSaving"
    />
  </div>
</template>
