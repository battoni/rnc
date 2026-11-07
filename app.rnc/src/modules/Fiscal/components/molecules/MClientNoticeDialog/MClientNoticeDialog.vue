<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ClientEvent, ReadinessDetail } from '../../../interfaces';
import { useFiscalFormat } from '../../../composables';
import { RegisterNoticeService } from '../../../services';

/*
 * "Avisar cliente" (rnc-2 R20, D12, the demo's `nfeMail`), inside the view's `MMainDialog`: the
 * e-mail Franco would send, written here from the client's counts (W84) — the window's issued
 * invoices, the % complete, the invoices at risk of 1115 and the emitting system —, "Copiar texto"
 * and "Registrar aviso". Nothing is sent: clients carry no e-mail. Registering writes ONE
 * `nfe.aviso` line (who and when) and swaps the button for that line; the view gets the line too.
 */
interface Props {
  client: ReadinessDetail;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  onClose: [];
  onRegistered: [event: ClientEvent];
}>();

const COPIED_FEEDBACK_MS = 2000;

const { formatCount, formatMoment, formatPercent } = useFiscalFormat();
const { t } = useI18n();

const copyFailed = ref(false);
const hasCopied = ref(false);
const isRegistering = ref(false);
const registerFailed = ref(false);
const registered = ref<ClientEvent | null>(null);

const copyText = computed(() => paragraphs.value.join('\n\n'));

const paragraphs = computed(() => {
  const { at_risk: atRisk, complete_pct: completePct, invoices, name, system, window_days: days } = props.client;
  const hasInvoices = invoices > 0;

  if (!hasInvoices)
    return [
      t('fiscal.notice.greeting', { name }),
      t('fiscal.notice.noNotes', { days }),
      t('fiscal.notice.ask'),
      t('fiscal.notice.signature'),
    ];

  const counts = t('fiscal.notice.counts', { days, invoices: formatCount(invoices), pct: formatPercent(completePct) });
  const risk = atRisk > 0 ? ` ${t('fiscal.notice.risk', { count: formatCount(atRisk) }, atRisk)}` : '';

  return [
    t('fiscal.notice.greeting', { name }),
    `${counts}${risk}`,
    system ? t('fiscal.notice.fix', { system }) : t('fiscal.notice.fixNoSystem'),
    t('fiscal.notice.ask'),
    t('fiscal.notice.signature'),
  ];
});

const registeredLine = computed(() => {
  const event = registered.value;

  if (!event) return null;

  const at = formatMoment(event.at);

  return event.user
    ? t('fiscal.notice.registered', { at, name: event.user.name })
    : t('fiscal.notice.registeredSystem', { at });
});

// EVENTS
function onCopy() {
  copyFailed.value = false;

  navigator.clipboard
    .writeText(copyText.value)
    .then(onCopied)
    .catch(() => (copyFailed.value = true));
}

function onCopied() {
  hasCopied.value = true;
  setTimeout(() => (hasCopied.value = false), COPIED_FEEDBACK_MS);
}

function onRegister() {
  isRegistering.value = true;
  registerFailed.value = false;

  RegisterNoticeService(props.client.id)
    .then((response) => onRegistered(response.data))
    .catch(() => (registerFailed.value = true))
    .finally(() => (isRegistering.value = false));
}

function onRegistered(event: ClientEvent) {
  registered.value = event;
  emit('onRegistered', event);
}
</script>

<template>
  <div
    class="flex flex-col gap-5"
    data-testid="client-notice"
    :data-client="client.id"
  >
    <p
      class="bg-info-50 text-info-800 flex items-start gap-2 rounded-md px-3 py-2 text-sm"
      data-testid="notice-flag"
    >
      <i
        aria-hidden="true"
        class="pi pi-info-circle mt-0.5"
      />

      {{ $t('fiscal.notice.flag') }}
    </p>

    <article
      class="border-line bg-panel motion-enter flex flex-col gap-3 rounded-lg border p-5"
      data-testid="notice-text"
    >
      <p
        v-for="(paragraph, index) in paragraphs"
        class="text-body text-sm leading-relaxed break-words"
        :key="index"
      >
        {{ paragraph }}
      </p>
    </article>

    <p
      v-if="copyFailed"
      class="bg-danger-50 text-danger-800 rounded-md px-3 py-2 text-sm"
      role="alert"
    >
      {{ $t('fiscal.notice.copyFailed') }}
    </p>

    <p
      v-if="registeredLine"
      aria-live="polite"
      class="bg-success-50 text-success-800 motion-enter flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
      data-testid="notice-registered"
    >
      <i
        aria-hidden="true"
        class="pi pi-check-circle"
      />

      {{ registeredLine }}
    </p>

    <p
      v-if="registerFailed"
      class="bg-danger-50 text-danger-800 rounded-md px-3 py-2 text-sm"
      role="alert"
    >
      {{ $t('fiscal.notice.registerFailed') }}
    </p>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <Button
        severity="secondary"
        type="button"
        :label="$t('fiscal.notice.close')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="notice-copy"
        severity="secondary"
        type="button"
        :icon="hasCopied ? 'pi pi-check' : 'pi pi-copy'"
        :label="hasCopied ? $t('fiscal.notice.copied') : $t('fiscal.notice.copy')"
        @click="onCopy"
      />

      <Button
        v-if="!registered"
        data-testid="notice-register"
        icon="pi pi-bookmark"
        type="button"
        :label="$t('fiscal.notice.register')"
        :loading="isRegistering"
        @click="onRegister"
      />
    </div>
  </div>
</template>
