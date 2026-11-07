<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { PortalReferral } from '../../../interfaces';
import { formatBrazilPhone, formatReal, translateError } from '@Helpers';
import { useProfileLine } from '@DiagnosticModule';
import { MOVES_WITH_FORM, TRANSITION_ERROR_KEYS } from '../../../constants';
import { REFERRAL_STATUS } from '../../../enums';
import { TransitionReferralService } from '../../../services';

/*
 * A referral in the partner's inbox (rnc-2 R6–R8, the demo's `referralCardView`): the company, its
 * profile, the items the partner's specialty covers, the score, Franco's message and the SLA chip;
 * the lead's contact only once the API sends it (from the accept on — D3), Franco's own for a
 * portfolio client; and the moves its status allows (R7). A move that needs nothing is the card's
 * own call; the two that need input — the meeting, the contract value — are asked of the inbox,
 * which opens their form. A refusal is the API's message in a warn toast (celer-07, row action),
 * and an `invalid_transition` also asks the inbox to read the list again: the card was stale.
 */
interface MoveError {
  response?: { data?: { code?: string; errors?: Record<string, string[]>; message?: string } };
  translatedMessage?: string;
}

interface CardAction {
  isPrimary: boolean;
  label: string;
  to: REFERRAL_STATUS;
}

const emit = defineEmits<{
  onAskDeal: [referral: PortalReferral];
  onAskMeeting: [referral: PortalReferral];
  onMoved: [referral: PortalReferral];
  onStale: [];
}>();

const props = defineProps<{
  firmName: string;
  referral: PortalReferral;
}>();

const TOAST_LIFE_MS = 4000;
const PORTAL_TIME_ZONE = 'America/Sao_Paulo';

const ACTIONS: Record<REFERRAL_STATUS, { isPrimary: boolean; to: REFERRAL_STATUS }[]> = {
  [REFERRAL_STATUS.ENVIADO]: [
    { isPrimary: true, to: REFERRAL_STATUS.ACEITO },
    { isPrimary: false, to: REFERRAL_STATUS.RECUSADO },
  ],
  [REFERRAL_STATUS.ACEITO]: [{ isPrimary: true, to: REFERRAL_STATUS.CONTATO }],
  [REFERRAL_STATUS.CONTATO]: [{ isPrimary: true, to: REFERRAL_STATUS.REUNIAO }],
  [REFERRAL_STATUS.REUNIAO]: [{ isPrimary: true, to: REFERRAL_STATUS.PROPOSTA }],
  [REFERRAL_STATUS.PROPOSTA]: [{ isPrimary: true, to: REFERRAL_STATUS.FECHADO }],
  [REFERRAL_STATUS.FECHADO]: [],
  [REFERRAL_STATUS.RECUSADO]: [],
};

const { profileLine } = useProfileLine(() => ({ ...props.referral.profile, b2b: null }));
const { t } = useI18n();
const toast = useToast();

const movingTo = ref<REFERRAL_STATUS | null>(null);

const contact = computed(() => props.referral.contact);

const actions = computed<CardAction[]>(() =>
  (ACTIONS[props.referral.status] ?? []).map((action) => ({
    ...action,
    label: t(`portal.actions.${action.to}`),
  }))
);

const standing = computed(() => {
  const { closed_at, deal_value_cents, meeting_at, status, status_at } = props.referral;

  if (status === REFERRAL_STATUS.REUNIAO && meeting_at) {
    return t('portal.referral.meetingAt', { when: formatMoment(meeting_at, true) });
  }

  if (status === REFERRAL_STATUS.FECHADO) {
    return t('portal.referral.closedLine', {
      date: formatMoment(closed_at ?? status_at ?? props.referral.received_at, false),
      value: formatReal(deal_value_cents ?? 0),
    });
  }

  if (status === REFERRAL_STATUS.RECUSADO) {
    return t('portal.referral.declinedLine', {
      date: formatMoment(status_at ?? props.referral.received_at, false),
      firm: props.firmName,
    });
  }

  return '';
});

// HELPERS
function formatMoment(iso: string, withTime: boolean): string {
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    timeZone: PORTAL_TIME_ZONE,
    ...(withTime ? { hour: '2-digit', hourCycle: 'h23', minute: '2-digit', weekday: 'short' } : { year: 'numeric' }),
  }).format(new Date(iso));
}

function receivedOn(): string {
  return formatMoment(props.referral.received_at, true);
}

function translateFailure(failure: MoveError): string {
  const data = failure.response?.data;
  const codeKey = TRANSITION_ERROR_KEYS[data?.code ?? ''];
  const firstError = codeKey ?? Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message;

  return firstError ? translateError(firstError) : (failure.translatedMessage ?? translateError('errors.unexpected'));
}

// EVENTS
function onAction(to: REFERRAL_STATUS) {
  if (MOVES_WITH_FORM.includes(to)) {
    if (to === REFERRAL_STATUS.REUNIAO) emit('onAskMeeting', props.referral);
    else emit('onAskDeal', props.referral);

    return;
  }

  movingTo.value = to;

  TransitionReferralService(props.referral.id, { to })
    .then((response) => emit('onMoved', response.data))
    .catch(onRefused)
    .finally(() => (movingTo.value = null));
}

function onRefused(failure: MoveError) {
  toast.add({ life: TOAST_LIFE_MS, severity: 'warn', summary: translateFailure(failure) });

  if (failure.response?.data?.code) emit('onStale');
}
</script>

<template>
  <article
    class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-5"
    data-testid="portal-referral"
    :data-referral="props.referral.id"
    :data-status="props.referral.status"
  >
    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex min-w-0 flex-col gap-1">
        <h3 class="text-heading text-lg font-semibold break-words">
          {{ props.referral.company ?? $t('pipeline.anonymous') }}
        </h3>

        <p
          v-if="profileLine"
          class="text-muted text-sm"
        >
          {{ profileLine }}
        </p>
      </div>

      <div class="flex shrink-0 flex-wrap items-center gap-2">
        <span class="bg-info-50 text-info-800 rounded-sm px-2 py-0.5 text-xs font-semibold">
          {{ $t(`portal.statuses.${props.referral.status}`) }}
        </span>

        <span class="bg-primary-100 text-primary-950 rounded-sm px-2 py-0.5 text-xs font-semibold tabular-nums">
          {{ $t('portal.referral.score', { score: props.referral.score }) }}
        </span>
      </div>
    </header>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
      <MSlaChip :sla="props.referral.sla" />

      <span class="text-muted text-xs tabular-nums">
        {{ $t('portal.referral.received', { when: receivedOn() }) }}
      </span>
    </div>

    <dl
      v-if="contact"
      class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-[max-content_1fr]"
      data-testid="referral-contact"
    >
      <dt class="text-muted">{{ $t('portal.referral.contact') }}</dt>

      <dd class="text-body min-w-0 break-words">
        {{
          contact.via === 'firm'
            ? $t('portal.referral.viaFirm', { firm: contact.name ?? props.firmName })
            : contact.name
        }}
      </dd>

      <template v-if="contact.phone">
        <dt class="text-muted">{{ $t('portal.referral.phone') }}</dt>

        <dd class="text-body tabular-nums">
          <a
            class="hover:underline"
            :href="`tel:+${contact.phone.replace(/\D/g, '')}`"
          >
            {{ formatBrazilPhone(contact.phone) }}
          </a>
        </dd>
      </template>

      <template v-if="contact.email">
        <dt class="text-muted">{{ $t('portal.referral.email') }}</dt>

        <dd class="text-body min-w-0 break-words">
          <a
            class="hover:underline"
            :href="`mailto:${contact.email}`"
          >
            {{ contact.email }}
          </a>
        </dd>
      </template>
    </dl>

    <p
      v-else-if="props.referral.status === REFERRAL_STATUS.ENVIADO"
      class="text-muted flex items-start gap-2 text-sm"
    >
      <i
        aria-hidden="true"
        class="pi pi-lock mt-0.5"
      />

      <span>{{ $t('portal.referral.contactAfterAccept') }}</span>
    </p>

    <div
      v-if="props.referral.covers.length > 0"
      class="flex flex-col gap-2"
    >
      <span class="text-muted text-xs">{{ $t('portal.referral.covers') }}</span>

      <ul
        class="flex flex-wrap gap-2"
        :aria-label="$t('portal.referral.covers')"
      >
        <li
          v-for="title in props.referral.covers"
          class="border-line text-body rounded-sm border px-2 py-0.5 text-xs"
          :key="title"
        >
          {{ title }}
        </li>
      </ul>
    </div>

    <blockquote
      class="border-line text-body border-l-2 pl-3 text-sm break-words whitespace-pre-line"
      data-testid="referral-message"
    >
      {{ props.referral.message }}
    </blockquote>

    <div class="flex flex-wrap items-center gap-3">
      <p
        v-if="standing"
        class="text-muted text-sm"
        data-testid="referral-standing"
      >
        {{ standing }}
      </p>

      <Button
        v-for="action in actions"
        data-testid="referral-action"
        :key="action.to"
        size="small"
        :data-to="action.to"
        :disabled="movingTo !== null && movingTo !== action.to"
        :label="action.label"
        :loading="movingTo === action.to"
        :severity="action.isPrimary ? undefined : 'secondary'"
        @click="onAction(action.to)"
      />
    </div>
  </article>
</template>
