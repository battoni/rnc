<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ReferralWritten } from '../../../interfaces';
import { translateError } from '@Helpers';
import { REFERRAL_ERROR_KEYS, REFERRAL_MESSAGE_MAX_LENGTH } from '../../../constants';
import { ReferLeadService } from '../../../services';

/*
 * "Encaminhar" with a message (rnc-1 R22): opened inline under the partner it goes to, the message
 * prefilled with the demo's sentence. It owns the call and shows the API's refusal inline — a lead
 * whose consent was withdrawn meanwhile is a 422 `consent_required` — and the panel applies the
 * answer (celer-07, form molecule contract).
 */
interface ReferError {
  response?: { data?: { code?: string; errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [written: ReferralWritten];
}>();

const props = defineProps<{
  company: string | null;
  leadId: number;
  partnerId: number;
  partnerName: string;
}>();

const { t } = useI18n();

const error = ref<string | null>(null);
const isSaving = ref(false);
const message = ref(t('partners.routing.defaultMessage', { company: props.company ?? t('pipeline.anonymous') }));

const formId = computed(() => `referral-form-${props.partnerId}`);
const messageId = computed(() => `referral-message-${props.partnerId}`);
const trimmedMessage = computed(() => message.value.trim());

// EVENTS
function onSubmit() {
  if (!trimmedMessage.value) {
    error.value = t('partners.routing.messageRequired');
    return;
  }

  const payload = { message: trimmedMessage.value, partner_id: props.partnerId };

  isSaving.value = true;
  error.value = null;

  ReferLeadService(props.leadId, payload)
    .then((response) => emit('onSuccess', response.data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: ReferError) {
  const codeKey = REFERRAL_ERROR_KEYS[failure.response?.data?.code ?? ''];
  const firstError = codeKey ?? Object.values(failure.response?.data?.errors ?? {})[0]?.[0];

  error.value = firstError
    ? translateError(firstError)
    : (failure.translatedMessage ?? translateError('errors.unexpected'));
}
</script>

<template>
  <form
    novalidate
    :id="formId"
    class="bg-panel-muted flex flex-col gap-3 rounded-md p-4"
    data-testid="referral-form"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        :for="messageId"
      >
        {{ $t('partners.routing.messageLabel', { partner: props.partnerName }) }}
      </label>

      <Textarea
        v-model="message"
        autofocus
        autoResize
        :id="messageId"
        class="w-full"
        data-testid="referral-message"
        rows="3"
        :invalid="!!error"
        :maxlength="REFERRAL_MESSAGE_MAX_LENGTH"
      />

      <p class="text-muted text-xs">{{ $t('partners.routing.privacyNote') }}</p>
    </div>

    <AFormError :error />

    <div class="flex flex-wrap justify-end gap-3">
      <Button
        severity="secondary"
        size="small"
        :label="$t('partners.routing.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="referral-send"
        icon="pi pi-send"
        size="small"
        type="submit"
        :label="$t('partners.routing.send')"
        :loading="isSaving"
      />
    </div>
  </form>
</template>
