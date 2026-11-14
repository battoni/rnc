<script setup lang="ts">
import type { DiagnosticContactDraft } from '../../../interfaces';
import { SITE_ROUTE_NAMES } from '@SiteModule';

/*
 * The contact, asked right after step 1 (rnc-1 R11): the company, the person's name, one way to
 * reach them — WhatsApp/phone or e-mail — and the optional consent to be introduced to Franco's
 * partners, unticked until the visitor ticks it (AM12). The words of the consent are the API's, so
 * the text shown is the text stored. The fields are `useDiagnostic`'s contact draft, the one the
 * chat receptionist fills too (rnc-2 R14): what was typed in either shows in the other.
 *
 * rnc-4 F9b (R38): under the consent, a link to the firm's privacy notice — in a new tab, so the
 * diagnostic in progress stays, and outside the label, so following it never ticks the box (W107).
 */
const props = defineProps<{
  consentText: string;
  errorKey: string | null;
  isSaving: boolean;
}>();

const draft = defineModel<DiagnosticContactDraft>('draft', { required: true });

const emit = defineEmits<{ onSubmit: [] }>();

// EVENTS
function onSubmit() {
  emit('onSubmit');
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
        v-model="draft.company"
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
        v-model="draft.name"
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
          v-model="draft.phone"
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
          v-model="draft.email"
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
        v-model="draft.consent"
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

    <RouterLink
      class="touch-target text-muted hover:text-heading self-start text-xs underline underline-offset-4"
      data-testid="contact-privacy"
      rel="noopener"
      target="_blank"
      :to="{ name: SITE_ROUTE_NAMES.privacy }"
    >
      {{ $t('diagnostic.contact.privacy') }}
    </RouterLink>

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
