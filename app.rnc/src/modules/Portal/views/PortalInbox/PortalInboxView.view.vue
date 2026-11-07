<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PortalMe, PortalReferral } from '../../interfaces';
import { getI18nRouteName } from '@Helpers';
import { IN_PROGRESS_STATUSES, PORTAL_ROUTE_NAMES, SLA_HOURS } from '../../constants';
import { REFERRAL_STATUS, SLA_STATE } from '../../enums';
import { GetPortalMeService, GetPortalReferralsService } from '../../services';

/*
 * The partner's inbox (rnc-2 R3, R5–R8; the demo's `partnerInbox`), inside Franco's shell: it
 * names the partner the API says is signed in (`GET portal/me` — never the URL), counts Novos ·
 * Em andamento · Fechados · SLA estourado and lists "Próximas reuniões" from the list itself (W22:
 * the API sends no counts), and shows every referral as a card, in the API's order (open before
 * closed, newest first). A move lands in place: the card it answered is replaced, nothing is
 * fetched again — unless a card reports itself stale. The meeting and the closing ask for input,
 * so they open their form here, in the view's one `MMainDialog`.
 */
const PORTAL_TIME_ZONE = 'America/Sao_Paulo';
const MOTION_STAGGER_CAP = 6;
const HTTP_FORBIDDEN = 403;

const { t } = useI18n();

const dealFor = ref<PortalReferral | null>(null);
const dialogVisible = ref(false);
const hasFailed = ref(false);
const isLoading = ref(true);
const isNotPartner = ref(false);
const me = ref<PortalMe | null>(null);
const meetingFor = ref<PortalReferral | null>(null);
const referrals = ref<PortalReferral[]>([]);

const dialogTitle = computed(() => (dealFor.value ? 'portal.close.title' : 'portal.meeting.title'));
const firmName = computed(() => me.value?.firm.name ?? t('portal.firmFallback'));

const kpis = computed(() => {
  const count = (keep: (referral: PortalReferral) => boolean) => referrals.value.filter(keep).length;

  return [
    { key: 'novos', value: count((referral) => referral.status === REFERRAL_STATUS.ENVIADO) },
    { key: 'andamento', value: count((referral) => IN_PROGRESS_STATUSES.includes(referral.status)) },
    { key: 'fechados', value: count((referral) => referral.status === REFERRAL_STATUS.FECHADO) },
    { key: 'estourado', value: count((referral) => referral.sla.state === SLA_STATE.ESTOURADO) },
  ];
});

const meetings = computed(() =>
  referrals.value
    .filter((referral) => referral.status === REFERRAL_STATUS.REUNIAO && referral.meeting_at)
    .sort((first, second) => Date.parse(first.meeting_at!) - Date.parse(second.meeting_at!))
);

onMounted(onOpen);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;
  isNotPartner.value = false;

  GetPortalReferralsService()
    .then((response) => (referrals.value = response.data))
    .catch((error: { response?: { status?: number } }) => {
      if (error.response?.status === HTTP_FORBIDDEN) isNotPartner.value = true;
      else hasFailed.value = true;
    })
    .finally(() => (isLoading.value = false));
}

function formatMeeting(iso: string): string {
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    hour: '2-digit',
    hourCycle: 'h23',
    minute: '2-digit',
    month: '2-digit',
    timeZone: PORTAL_TIME_ZONE,
    weekday: 'short',
  }).format(new Date(iso));
}

/** A long inbox arrives in a few steps, not one per card: the stagger stops growing at the cap. */
function staggerOf(index: number): number {
  return Math.min(index, MOTION_STAGGER_CAP);
}

function replace(moved: PortalReferral) {
  referrals.value = referrals.value.map((referral) => (referral.id === moved.id ? moved : referral));
}

function closeDialog() {
  dialogVisible.value = false;
  dealFor.value = null;
  meetingFor.value = null;
}

// EVENTS
function onOpen() {
  load();

  GetPortalMeService()
    .then((response) => (me.value = response.data))
    .catch(() => (me.value = null));
}

function onAskMeeting(referral: PortalReferral) {
  dealFor.value = null;
  meetingFor.value = referral;
  dialogVisible.value = true;
}

function onAskDeal(referral: PortalReferral) {
  meetingFor.value = null;
  dealFor.value = referral;
  dialogVisible.value = true;
}

function onFormSuccess(moved: PortalReferral) {
  replace(moved);
  closeDialog();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="portal.title" />
    </template>

    <section
      v-if="me"
      class="motion-enter flex flex-col gap-2"
      data-testid="portal-home"
    >
      <h2
        class="text-heading text-lg font-semibold break-words"
        data-testid="portal-partner"
      >
        {{ $t('portal.greeting', { name: me.partner.name }) }}
      </h2>

      <p class="text-muted text-sm">
        {{ $t(`partners.specialties.${me.partner.specialty}`) }}
      </p>

      <p class="text-body text-sm">
        {{ $t('portal.description', { firm: me.firm.name, hours: SLA_HOURS }) }}
      </p>

      <RouterLink
        class="text-primary-700 hover:text-primary-800 w-fit text-sm font-semibold underline-offset-4 hover:underline"
        data-testid="portal-statement-link"
        :to="{ name: getI18nRouteName(PORTAL_ROUTE_NAMES.statement) }"
      >
        {{ $t('portal.statement.open') }}
      </RouterLink>
    </section>

    <div
      v-if="isLoading && referrals.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="5rem" />

      <Skeleton height="10rem" />
    </div>

    <div
      v-else-if="isNotPartner"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      data-testid="portal-no-partner"
      role="alert"
    >
      <p class="text-body">{{ $t('portal.noPartner') }}</p>
    </div>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('portal.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('portal.retry')"
        @click="load"
      />
    </div>

    <template v-else>
      <section
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
        :aria-label="$t('portal.kpis.label')"
      >
        <div
          v-for="(kpi, index) in kpis"
          class="bg-panel shadow-lift motion-enter flex flex-col gap-1 rounded-lg p-4"
          :key="kpi.key"
          :data-testid="`kpi-${kpi.key}`"
          :style="{ '--reveal-index': index }"
        >
          <span class="text-muted text-sm">{{ $t(`portal.kpis.${kpi.key}`) }}</span>

          <strong
            :class="[
              'text-2xl font-semibold tabular-nums',
              kpi.key === 'estourado' && kpi.value > 0 ? 'text-danger-700' : 'text-heading',
            ]"
          >
            {{ kpi.value }}
          </strong>
        </div>
      </section>

      <section
        class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5"
        data-testid="portal-meetings"
      >
        <h2 class="text-heading font-semibold">{{ $t('portal.meetings.title') }}</h2>

        <ul
          v-if="meetings.length > 0"
          class="divide-line flex flex-col divide-y"
        >
          <li
            v-for="referral in meetings"
            class="text-body flex flex-wrap gap-x-2 py-2 text-sm first:pt-0 last:pb-0"
            :key="referral.id"
          >
            <time
              class="text-heading font-medium tabular-nums"
              :datetime="referral.meeting_at!"
            >
              {{ formatMeeting(referral.meeting_at!) }}
            </time>

            <span class="break-words">· {{ referral.company ?? $t('pipeline.anonymous') }}</span>
          </li>
        </ul>

        <p
          v-else
          class="text-muted text-sm"
        >
          {{ $t('portal.meetings.empty') }}
        </p>
      </section>

      <div
        v-if="referrals.length === 0"
        class="bg-panel shadow-lift flex flex-col items-center gap-2 rounded-lg p-8 text-center"
        data-testid="portal-empty"
      >
        <i
          aria-hidden="true"
          class="pi pi-inbox text-subtle text-2xl"
        />

        <p class="text-heading font-semibold">{{ $t('portal.inbox.emptyTitle') }}</p>

        <p class="text-muted text-sm">{{ $t('portal.inbox.emptyText', { firm: firmName }) }}</p>
      </div>

      <section
        v-else
        class="flex min-w-0 flex-col gap-3"
        :aria-label="$t('portal.inbox.label')"
      >
        <MPortalReferralCard
          v-for="(referral, index) in referrals"
          class="motion-enter"
          :key="referral.id"
          :firmName
          :referral
          :style="{ '--reveal-index': staggerOf(index) }"
          @onAskDeal="onAskDeal"
          @onAskMeeting="onAskMeeting"
          @onMoved="replace"
          @onStale="load"
        />
      </section>
    </template>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        :title="dialogTitle"
      >
        <MReferralMeetingForm
          v-if="meetingFor"
          :key="`meeting-${meetingFor.id}`"
          :referral="meetingFor"
          @onClose="closeDialog"
          @onSuccess="onFormSuccess"
        />

        <MReferralCloseForm
          v-else-if="dealFor"
          :key="`deal-${dealFor.id}`"
          :referral="dealFor"
          @onClose="closeDialog"
          @onSuccess="onFormSuccess"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
