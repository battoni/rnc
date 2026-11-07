<script setup lang="ts">
import { ref } from 'vue';
import type { DiagnosticContactPayload } from '../../../interfaces';

/*
 * The contact, asked right after step 1 (rnc-1 R11): the company, the person's name, one way to
 * reach them — WhatsApp/phone or e-mail — and the optional consent to be introduced to Franco's
 * partners, unticked until the visitor ticks it (AM12). The words of the consent are the API's, so
 * the text shown is the text stored.
 */
const props = defineProps<{
  company: string | null;
  consentText: string;
  errorKey: string | null;
  isSaving: boolean;
}>();

const emit = defineEmits<{ onSubmit: [contact: DiagnosticContactPayload] }>();

const companyName = ref(props.company ?? '');
const consent = ref(false);
const email = ref('');
const name = ref('');
const phone = ref('');

// HELPERS
function orNull(value: string): string | null {
  return value.trim() || null;
}

// EVENTS
function onSubmit() {
  const CONTACT = {
    company: companyName.value.trim(),
    consent: consent.value,
    email: orNull(email.value),
    name: orNull(name.value),
    phone: orNull(phone.value),
  };

  emit('onSubmit', CONTACT);
}
</script>

<template>
  <form
    novalidate
    class="flex flex-col gap-4"
    data-testid="contact-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body">
      {{ $t('diagnostic.contact.lede') }}
    </p>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="contact-company"
      >
        {{ $t('diagnostic.contact.company') }}
      </label>

      <InputText
        v-model="companyName"
        id="contact-company"
        autocomplete="organization"
        data-testid="contact-company"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="contact-name"
      >
        {{ $t('diagnostic.contact.name') }}
      </label>

      <InputText
        v-model="name"
        id="contact-name"
        autocomplete="name"
        data-testid="contact-name"
      />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="contact-phone"
        >
          {{ $t('diagnostic.contact.phone') }}
        </label>

        <InputText
          v-model="phone"
          id="contact-phone"
          autocomplete="tel"
          data-testid="contact-phone"
          inputmode="tel"
          type="tel"
        />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="contact-email"
        >
          {{ $t('diagnostic.contact.email') }}
        </label>

        <InputText
          v-model="email"
          id="contact-email"
          autocomplete="email"
          data-testid="contact-email"
          inputmode="email"
          type="email"
        />
      </div>
    </div>

    <small class="text-muted text-xs">
      {{ $t('diagnostic.contact.hint') }}
    </small>

    <label
      class="bg-panel-muted flex cursor-pointer items-start gap-3 rounded-lg p-4 text-sm"
      data-testid="contact-consent"
      for="contact-consent"
    >
      <Checkbox
        v-model="consent"
        binary
        class="mt-0.5 shrink-0"
        input-id="contact-consent"
      />

      <span class="text-body flex flex-col gap-1">
        {{ props.consentText }}

        <span class="text-muted text-xs">
          {{ $t('diagnostic.contact.consentOptional') }}
        </span>
      </span>
    </label>

    <p
      v-if="props.errorKey"
      class="text-danger-700 text-sm"
      data-testid="contact-error"
      role="alert"
    >
      {{ $t(props.errorKey) }}
    </p>

    <div class="flex">
      <Button
        data-testid="contact-save"
        icon="pi pi-arrow-right"
        icon-pos="right"
        type="submit"
        :label="$t('diagnostic.actions.continue')"
        :loading="props.isSaving"
      />
    </div>
  </form>
</template>
