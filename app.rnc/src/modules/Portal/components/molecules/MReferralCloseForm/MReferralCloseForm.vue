<script setup lang="ts">
import { ref } from 'vue';
import type { PortalReferral } from '../../../interfaces';
import { toCents, translateError } from '@Helpers';
import { DEAL_VALUE_MAX_CENTS, TRANSITION_ERROR_KEYS } from '../../../constants';
import { REFERRAL_STATUS } from '../../../enums';
import { TransitionReferralService } from '../../../services';

/*
 * "Fechar contrato" (rnc-2 R7): the first-year contract value in reais, above zero, before the
 * move to `fechado`, in the inbox's `MMainDialog`. The value leaves as whole cents (`toCents`) —
 * the ledger (F4) computes the commission from it. The form owns the call and shows the API's
 * refusal inline; the inbox applies the answer (celer-07, form molecule contract).
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

const dealValue = ref<number | null>(null);
const error = ref<string | null>(null);
const isSaving = ref(false);

// EVENTS
function onSubmit() {
  const cents = dealValue.value === null ? 0 : toCents(dealValue.value);

  if (cents <= 0 || cents > DEAL_VALUE_MAX_CENTS) {
    error.value = translateError(
      cents <= 0 ? 'portal.referrals.dealValueRequired' : 'portal.referrals.dealValueInvalid'
    );
    return;
  }

  isSaving.value = true;
  error.value = null;

  TransitionReferralService(props.referral.id, { deal_value_cents: cents, to: REFERRAL_STATUS.FECHADO })
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
    id="close-form"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    data-testid="close-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body text-sm">
      {{ $t('portal.close.lede', { company: props.referral.company ?? $t('pipeline.anonymous') }) }}
    </p>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="deal-value"
      >
        {{ $t('portal.close.label') }}
      </label>

      <InputNumber
        v-model="dealValue"
        autofocus
        fluid
        currency="BRL"
        inputId="deal-value"
        locale="pt-BR"
        mode="currency"
        :invalid="!!error"
        :min="0"
      />
    </div>

    <AFormError :error />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('portal.close.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="close-confirm"
      form="close-form"
      type="submit"
      :label="$t('portal.close.confirm')"
      :loading="isSaving"
    />
  </div>
</template>
