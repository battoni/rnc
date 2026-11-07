<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { IssuedPartnerAccess, Partner, PartnerAccess, PartnerAccessPayload } from '../../../interfaces';
import { translateError } from '@Helpers';
import { PARTNER_ACCESS_ERROR_KEYS, PARTNER_ACCESS_LIMITS } from '../../../constants';
import {
  CreatePartnerAccessService,
  RegeneratePartnerPasswordService,
  RevokePartnerAccessService,
} from '../../../services';

/*
 * A partner's portal login (rnc-2 R2), from the registry: without an active one, a username and an
 * e-mail create it; with one, "Gerar nova senha" or "Revogar acesso". A generated password is shown
 * ONCE, here, with a copy button — `onChanged` hands the view the login without it, so it never
 * lives anywhere but this panel. It owns the calls and shows the API's refusals (celer-07, form
 * molecule contract); a revoke is reversible (create again), so no confirm popup.
 */
type FieldErrors = Partial<Record<keyof PartnerAccessPayload, string>>;

interface AccessError {
  response?: { data?: { code?: string; errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onChanged: [access: PartnerAccess];
  onClose: [];
}>();

const props = defineProps<{
  partner: Partner;
}>();

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const COPIED_FEEDBACK_MS = 2000;

const GRANTED_FORMAT: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit', year: 'numeric' };

const { locale, t } = useI18n();

const access = ref<PartnerAccess | null>(props.partner.access);
const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const hasCopied = ref(false);
const isBusy = ref(false);
const password = ref<string | null>(null);

const form = reactive<PartnerAccessPayload>({
  email: props.partner.access?.email ?? props.partner.email,
  username: props.partner.access?.username ?? '',
});

const isActive = computed(() => !!access.value?.active);
const grantedAt = computed(() =>
  access.value?.granted_at
    ? new Intl.DateTimeFormat(locale.value, GRANTED_FORMAT).format(new Date(access.value.granted_at))
    : ''
);

// HELPERS
function validate(): FieldErrors {
  const found: FieldErrors = {};

  if (!form.username.trim()) found.username = t('partners.access.required');
  if (!form.email.trim()) found.email = t('partners.access.required');
  else if (!EMAIL_PATTERN.test(form.email.trim())) found.email = t('partners.access.emailInvalid');

  return found;
}

function withoutPassword(issued: IssuedPartnerAccess): PartnerAccess {
  return {
    active: issued.active,
    email: issued.email,
    granted_at: issued.granted_at,
    partner_id: issued.partner_id,
    revoked_at: issued.revoked_at,
    user_id: issued.user_id,
    username: issued.username,
  };
}

function run<T>(request: Promise<{ data: T }>, onDone: (data: T) => void) {
  formError.value = null;
  isBusy.value = true;

  request
    .then((response) => onDone(response.data))
    .catch(onFailed)
    .finally(() => (isBusy.value = false));
}

// EVENTS
function onSubmit() {
  errors.value = validate();

  if (Object.keys(errors.value).length > 0) return;

  const payload: PartnerAccessPayload = { email: form.email.trim(), username: form.username.trim().toLowerCase() };

  run(CreatePartnerAccessService(props.partner.id, payload), onIssued);
}

function onRegenerate() {
  run(RegeneratePartnerPasswordService(props.partner.id), onIssued);
}

function onRevoke() {
  run(RevokePartnerAccessService(props.partner.id), (revoked) => {
    access.value = revoked;
    emit('onChanged', revoked);
  });
}

function onIssued(issued: IssuedPartnerAccess) {
  const login = withoutPassword(issued);

  access.value = login;
  password.value = issued.password;
  emit('onChanged', login);
}

function onCopy() {
  if (!password.value) return;

  navigator.clipboard.writeText(password.value).then(() => {
    hasCopied.value = true;
    setTimeout(() => (hasCopied.value = false), COPIED_FEEDBACK_MS);
  });
}

function onFailed(failure: AccessError) {
  const knownRefusal = PARTNER_ACCESS_ERROR_KEYS[failure.response?.data?.code ?? ''];
  const fieldErrors = failure.response?.data?.errors ?? {};
  const fields = Object.keys(fieldErrors);

  if (knownRefusal) {
    formError.value = t(knownRefusal);
    return;
  }

  if (fields.length === 0) {
    formError.value = failure.translatedMessage ?? translateError('errors.unexpected');
    return;
  }

  errors.value = Object.fromEntries(fields.map((field) => [field, translateError(fieldErrors[field]?.[0] ?? '')]));
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[500px] flex-col gap-4">
    <p class="text-body text-sm">{{ $t('partners.access.lede') }}</p>

    <section
      v-if="password"
      class="bg-panel-muted flex flex-col gap-3 rounded-lg p-4"
      data-testid="access-issued"
    >
      <span class="text-muted text-sm">{{ $t('partners.access.passwordTitle') }}</span>

      <div class="flex flex-wrap items-center gap-3">
        <code
          class="text-heading font-mono text-lg font-semibold break-all"
          data-testid="access-password"
        >
          {{ password }}
        </code>

        <Button
          data-testid="access-copy"
          severity="secondary"
          size="small"
          :icon="hasCopied ? 'pi pi-check' : 'pi pi-copy'"
          :label="hasCopied ? $t('partners.access.copied') : $t('partners.access.copy')"
          @click="onCopy"
        />
      </div>

      <p
        class="text-body text-sm"
        role="status"
      >
        {{ $t('partners.access.passwordOnce') }}
      </p>
    </section>

    <section
      v-if="isActive && access"
      class="flex flex-col gap-3"
      data-testid="access-current"
    >
      <p class="text-heading font-semibold break-all">
        {{ $t('partners.access.current', { email: access.email, username: access.username }) }}
      </p>

      <p
        v-if="grantedAt"
        class="text-muted text-xs"
      >
        {{ $t('partners.access.grantedAt', { date: grantedAt }) }}
      </p>

      <div
        v-if="!password"
        class="flex flex-wrap gap-3"
      >
        <Button
          data-testid="access-regenerate"
          icon="pi pi-refresh"
          severity="secondary"
          :label="$t('partners.access.regenerate')"
          :loading="isBusy"
          @click="onRegenerate"
        />

        <Button
          data-testid="access-revoke"
          icon="pi pi-ban"
          severity="warn"
          :disabled="isBusy"
          :label="$t('partners.access.revoke')"
          @click="onRevoke"
        />
      </div>

      <AFormError :error="formError" />
    </section>

    <form
      v-else
      novalidate
      id="partner-access-form"
      class="flex flex-col gap-4"
      data-testid="access-form"
      @submit.prevent="onSubmit"
    >
      <p
        v-if="access && !access.active"
        class="text-muted text-sm"
      >
        {{ $t('partners.access.revokedNote') }}
      </p>

      <div class="flex flex-col gap-2">
        <label
          for="partner-access-username"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.username }]"
        >
          {{ $t('partners.access.username') }}
        </label>

        <InputText
          v-model="form.username"
          id="partner-access-username"
          autocapitalize="none"
          autocomplete="off"
          class="w-full"
          data-testid="access-username"
          :aria-invalid="!!errors.username"
          :invalid="!!errors.username"
          :maxlength="PARTNER_ACCESS_LIMITS.username"
        />

        <p class="text-muted text-xs">{{ $t('partners.access.usernameHint') }}</p>

        <AFormError :error="errors.username" />
      </div>

      <div class="flex flex-col gap-2">
        <label
          for="partner-access-email"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.email }]"
        >
          {{ $t('partners.access.email') }}
        </label>

        <InputText
          v-model="form.email"
          id="partner-access-email"
          class="w-full"
          data-testid="access-email"
          inputmode="email"
          type="email"
          :aria-invalid="!!errors.email"
          :invalid="!!errors.email"
          :maxlength="PARTNER_ACCESS_LIMITS.email"
        />

        <AFormError :error="errors.email" />
      </div>

      <AFormError :error="formError" />
    </form>
  </div>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      v-if="password || isActive"
      data-testid="access-done"
      :label="$t('partners.access.done')"
      @click="emit('onClose')"
    />

    <template v-else>
      <Button
        severity="secondary"
        :label="$t('partners.access.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="access-create"
        form="partner-access-form"
        type="submit"
        :label="$t('partners.access.create')"
        :loading="isBusy"
      />
    </template>
  </div>
</template>
