<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import type { LeadDetail, LeadMove, LeadTimelineEvent, PendingLostMove } from '../../interfaces';
import { getI18nRouteName, toRouteId, translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { contactLinks, useLeadFormat } from '../../composables';
import { BOARD_STAGES, PIPELINE_PERMISSIONS, PIPELINE_ROUTE_NAMES } from '../../constants';
import { LEAD_STAGE, MOVE_VIA } from '../../enums';
import { GetLeadService, MoveLeadStageService } from '../../services';

/*
 * The lead detail (rnc-1 R19): who the lead is and how to reach them (real `tel:`, `mailto:` and
 * `wa.me` links), the profile, the full diagnostic the visitor only gets in the meeting, the consent
 * answer as given, the meeting, the team's notes and the timeline. The stage moves from here too
 * (`via: ficha`); perdido asks for the reason. Under the diagnostic, the routing panel (F6, R22,
 * the Partner module's `MLeadRouting`): a referral lands on the timeline, the stage stays.
 */
interface LoadError {
  response?: { status?: number };
}

interface MoveError {
  response?: { status?: number };
  translatedMessage?: string;
}

const HTTP_NOT_FOUND = 404;
const HTTP_UNPROCESSABLE = 422;
const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const route = useRoute();
const { t } = useI18n();
const toast = useToast();
const { formatDate, formatMeeting, formatMoment, stageLabel } = useLeadFormat();

const dialogVisible = ref(false);
const hasFailed = ref(false);
const isLoading = ref(true);
const isMissing = ref(false);
const isMoving = ref(false);
const lead = ref<LeadDetail | null>(null);
const pendingLost = ref<PendingLostMove | null>(null);

const canUpdate = computed(() => userStore.hasPermission(PIPELINE_PERMISSIONS.update));
const leadId = computed(() => toRouteId(route.params.id));
const links = computed(() => contactLinks(lead.value?.phone ?? null, lead.value?.email ?? null));
const stageOptions = computed(() => BOARD_STAGES.map((stage) => ({ label: stageLabel(stage), value: stage })));

const profileRows = computed(() => {
  const current = lead.value;

  if (!current) return [];

  const { profile } = current;
  const empty = t('pipeline.lead.profile.empty');
  const yesNo = (value: boolean) => t(value ? 'pipeline.lead.profile.yes' : 'pipeline.lead.profile.no');

  return [
    { key: 'cnpj', value: current.cnpj ? formatCnpj(current.cnpj) : empty },
    { key: 'sector', value: profile.sector ? t(`diagnostic.sectors.${profile.sector}`) : empty },
    { key: 'regime', value: profile.regime ? t(`diagnostic.regimes.${profile.regime}`) : empty },
    { key: 'band', value: profile.band ? t(`diagnostic.bands.${profile.band}`) : empty },
    { key: 'b2b', value: profile.b2b !== null ? `${profile.b2b}%` : empty },
    { key: 'incentivos', value: yesNo(profile.incentivos) },
    { key: 'exporta', value: yesNo(profile.exporta) },
    { key: 'path', value: t(`pipeline.lead.profile.paths.${current.path}`) },
    { key: 'source', value: t(`pipeline.sources.${current.source}`) },
  ];
});

watch(leadId, load);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  isMissing.value = false;
  hasFailed.value = false;

  GetLeadService(leadId.value)
    .then((response) => (lead.value = response.data))
    .catch(onLoadFailed)
    .finally(() => (isLoading.value = false));
}

function formatCnpj(cnpj: string): string {
  return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

function formatPhone(phone: string): string {
  return phone.replace(/^55(\d{2})(\d{4,5})(\d{4})$/, '($1) $2-$3');
}

function applyMove(move: LeadMove) {
  if (!lead.value) return;

  lead.value.stage = move.lead.stage;
  lead.value.lost_reason = move.lead.lost_reason;
  lead.value.timeline = [move.event, ...lead.value.timeline];

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('pipeline.moved', {
      company: lead.value.company ?? t('pipeline.anonymous'),
      from: stageLabel(String(move.event.data.from ?? '')),
      to: stageLabel(move.lead.stage),
    }),
  });
}

// EVENTS
function onLoadFailed(failure: LoadError) {
  lead.value = null;

  if (failure.response?.status === HTTP_NOT_FOUND || Number.isNaN(leadId.value)) {
    isMissing.value = true;
    return;
  }

  hasFailed.value = true;
}

function onStageChange(stage: LEAD_STAGE) {
  const current = lead.value;

  if (!current || stage === current.stage || isMoving.value) return;

  if (stage === LEAD_STAGE.PERDIDO) {
    pendingLost.value = { company: current.company, id: current.id, via: MOVE_VIA.DETAIL };
    dialogVisible.value = true;
    return;
  }

  const payload = { stage, via: MOVE_VIA.DETAIL };

  isMoving.value = true;

  MoveLeadStageService(current.id, payload)
    .then((response) => applyMove(response.data))
    .catch(onMoveFailed)
    .finally(() => (isMoving.value = false));
}

function onMoveFailed(failure: MoveError) {
  toast.add({
    life: TOAST_LIFE_MS,
    severity: failure.response?.status === HTTP_UNPROCESSABLE ? 'warn' : 'error',
    summary: failure.translatedMessage ?? translateError('errors.unexpected'),
  });
}

function onLostSaved(move: LeadMove) {
  dialogVisible.value = false;
  pendingLost.value = null;
  applyMove(move);
}

function onReferred(event: LeadTimelineEvent) {
  if (!lead.value) return;

  lead.value.timeline = [event, ...lead.value.timeline];
}

function onNoteSaved(note: LeadTimelineEvent) {
  if (!lead.value) return;

  lead.value.notes = [note, ...lead.value.notes];
  lead.value.timeline = [note, ...lead.value.timeline];

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('pipeline.lead.notes.saved') });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="pipeline.lead.title">
        <template
          v-if="!dialogVisible"
          #actions
        >
          <RouterLink
            v-slot="{ navigate }"
            custom
            :to="{ name: getI18nRouteName(PIPELINE_ROUTE_NAMES.board) }"
          >
            <Button
              data-testid="back-to-board"
              icon="pi pi-arrow-left"
              severity="secondary"
              :label="$t('pipeline.lead.back')"
              @click="navigate"
            />
          </RouterLink>
        </template>
      </ThePageHeader>
    </template>

    <div
      v-if="isLoading && !lead"
      class="flex flex-col gap-4"
    >
      <Skeleton height="6rem" />

      <Skeleton height="18rem" />
    </div>

    <AEmptyState
      v-else-if="isMissing"
      icon="pi pi-search"
      :description="$t('pipeline.lead.missingText')"
      :title="$t('pipeline.lead.missingTitle')"
    />

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('pipeline.lead.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('pipeline.retry')"
        @click="load"
      />
    </div>

    <article
      v-else-if="lead"
      class="flex min-w-0 flex-col gap-6"
      data-testid="lead-detail"
      :data-lead="lead.id"
    >
      <header
        class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5 lg:flex-row lg:items-center lg:justify-between"
      >
        <div class="flex min-w-0 flex-col gap-1.5">
          <div class="flex flex-wrap items-center gap-2">
            <h2
              class="text-heading text-2xl font-bold tracking-tight break-words"
              data-testid="lead-company"
            >
              {{ lead.company ?? $t('pipeline.anonymous') }}
            </h2>

            <!-- rnc-3 R4: the face of Franco the visitor saw. -->
            <span
              v-if="lead.brand"
              class="bg-primary-50 text-primary-950 inline-flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs font-semibold"
              data-testid="lead-brand"
              :title="$t('pipeline.brandChip', { name: lead.brand.short_name })"
            >
              <i
                aria-hidden="true"
                class="pi pi-palette text-xs"
              />{{ lead.brand.short_name }}
            </span>
          </div>

          <p class="text-muted flex flex-wrap items-center gap-2 text-sm">
            <span>
              {{
                $t('pipeline.lead.since', {
                  date: formatDate(lead.created_at),
                  source: $t(`pipeline.sources.${lead.source}`),
                })
              }}
            </span>

            <MLeadChannelChip :channel="lead.channel" />
          </p>

          <p
            v-if="lead.lost_reason"
            class="text-danger-800 bg-danger-50 w-fit rounded-sm px-2 py-1 text-sm"
            data-testid="lead-lost-reason"
          >
            {{ $t('pipeline.lead.lostBecause', { reason: lead.lost_reason }) }}
          </p>
        </div>

        <div class="flex shrink-0 flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="lead-stage"
          >
            {{ $t('pipeline.lead.moveTo') }}
          </label>

          <Select
            class="w-full lg:w-60"
            data-testid="stage-select"
            inputId="lead-stage"
            optionLabel="label"
            optionValue="value"
            :disabled="!canUpdate || isMoving"
            :modelValue="lead.stage"
            :options="stageOptions"
            @update:modelValue="onStageChange"
          />
        </div>
      </header>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
          <section class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5">
            <h3 class="text-heading text-lg font-semibold">
              {{
                lead.diagnosis.items.length > 0
                  ? `${$t('pipeline.lead.diagnosis.title')}: ${$t('pipeline.lead.diagnosis.summary', {
                      opportunities: $t('diagnostic.result.opportunities', lead.diagnosis.opportunities),
                      risks: $t('diagnostic.result.risks', lead.diagnosis.risks),
                    })}`
                  : $t('pipeline.lead.diagnosis.title')
              }}
            </h3>

            <MLeadDiagnosis :diagnosis="lead.diagnosis" />
          </section>

          <MLeadRouting
            :canRefer="canUpdate"
            :company="lead.company"
            :leadId="lead.id"
            @onReferred="onReferred"
          />

          <section class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5">
            <h3 class="text-heading text-lg font-semibold">{{ $t('pipeline.lead.profile.title') }}</h3>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div
                v-for="row in profileRows"
                class="border-line flex flex-col gap-0.5 border-b pb-2"
                :key="row.key"
              >
                <dt class="text-muted text-xs">{{ $t(`pipeline.lead.profile.${row.key}`) }}</dt>

                <dd class="text-heading text-sm font-medium break-words">{{ row.value }}</dd>
              </div>
            </dl>
          </section>

          <section class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5">
            <h3 class="text-heading text-lg font-semibold">{{ $t('pipeline.lead.timeline.title') }}</h3>

            <MLeadTimeline :events="lead.timeline" />
          </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
          <section
            class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5"
            data-testid="lead-contact"
          >
            <h3 class="text-heading text-lg font-semibold">{{ $t('pipeline.lead.contact.title') }}</h3>

            <p
              v-if="lead.contact_name"
              class="text-heading font-medium"
            >
              {{ lead.contact_name }}
            </p>

            <p
              v-if="lead.phone"
              class="text-body text-sm tabular-nums"
            >
              {{ formatPhone(lead.phone) }}
            </p>

            <p
              v-if="lead.email"
              class="text-body text-sm break-words"
            >
              {{ lead.email }}
            </p>

            <div
              v-if="links.phone || links.email"
              class="flex flex-wrap gap-3 pt-1"
            >
              <a
                v-if="links.phone"
                class="p-button p-button-secondary p-button-sm touch-target gap-2 no-underline"
                data-testid="contact-call"
                :href="links.phone"
              >
                <i
                  aria-hidden="true"
                  class="pi pi-phone"
                />

                {{ $t('pipeline.lead.contact.call') }}
              </a>

              <a
                v-if="links.whatsapp"
                class="p-button p-button-secondary p-button-sm touch-target gap-2 no-underline"
                data-testid="contact-whatsapp"
                rel="noopener noreferrer"
                target="_blank"
                :href="links.whatsapp"
              >
                <i
                  aria-hidden="true"
                  class="pi pi-whatsapp"
                />

                {{ $t('pipeline.lead.contact.whatsapp') }}
              </a>

              <a
                v-if="links.email"
                class="p-button p-button-secondary p-button-sm touch-target gap-2 no-underline"
                data-testid="contact-email"
                :href="links.email"
              >
                <i
                  aria-hidden="true"
                  class="pi pi-envelope"
                />

                {{ $t('pipeline.lead.contact.email') }}
              </a>
            </div>

            <p
              v-else
              class="text-muted text-sm"
            >
              {{ $t('pipeline.lead.contact.none') }}
            </p>
          </section>

          <section
            class="bg-panel shadow-lift flex flex-col gap-2 rounded-lg p-5"
            data-testid="lead-meeting"
          >
            <h3 class="text-heading text-lg font-semibold">{{ $t('pipeline.lead.meeting.title') }}</h3>

            <p
              v-if="lead.meeting"
              class="text-body text-sm"
            >
              {{
                $t('pipeline.lead.meeting.with', {
                  specialist: lead.meeting.specialist,
                  when: formatMeeting(lead.meeting.starts_at),
                })
              }}
            </p>

            <p
              v-else
              class="text-muted text-sm"
            >
              {{ $t('pipeline.lead.meeting.none') }}
            </p>
          </section>

          <section
            class="bg-panel shadow-lift flex flex-col gap-2 rounded-lg p-5"
            data-testid="lead-consent"
            :data-given="lead.consent ? String(lead.consent.given) : 'none'"
          >
            <h3 class="text-heading text-lg font-semibold">{{ $t('pipeline.lead.consent.title') }}</h3>

            <template v-if="lead.consent">
              <p
                :class="[
                  'flex items-center gap-2 text-sm font-semibold',
                  lead.consent.given ? 'text-success-800' : 'text-warn-800',
                ]"
              >
                <i
                  aria-hidden="true"
                  :class="lead.consent.given ? 'pi pi-check-circle' : 'pi pi-ban'"
                />

                {{ lead.consent.given ? $t('pipeline.lead.consent.given') : $t('pipeline.lead.consent.refused') }}
              </p>

              <blockquote class="border-line text-body border-l-2 pl-3 text-sm italic">
                “{{ lead.consent.text }}”
              </blockquote>

              <p class="text-muted text-xs">
                {{
                  $t('pipeline.lead.consent.when', {
                    date: formatMoment(lead.consent.given_at),
                    version: lead.consent.version,
                    via: $t(`pipeline.lead.consent.vias.${lead.consent.via}`),
                  })
                }}
              </p>
            </template>

            <p
              v-else
              class="text-muted text-sm"
            >
              {{ $t('pipeline.lead.consent.none') }}
            </p>
          </section>

          <section class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5">
            <h3 class="text-heading text-lg font-semibold">{{ $t('pipeline.lead.notes.title') }}</h3>

            <MLeadNoteForm
              v-if="canUpdate"
              :leadId="lead.id"
              @onSuccess="onNoteSaved"
            />

            <ul
              v-if="lead.notes.length > 0"
              class="flex flex-col gap-3"
              data-testid="notes"
            >
              <li
                v-for="note in lead.notes"
                class="bg-panel-muted flex flex-col gap-1 rounded-md p-3"
                data-testid="note"
                :key="note.id"
              >
                <span class="text-body text-sm break-words whitespace-pre-line">{{ note.data.text }}</span>

                <span class="text-muted text-xs">
                  {{ formatMoment(note.at) }}
                  ·
                  {{ note.user?.name ?? $t('pipeline.lead.timeline.visitor') }}
                </span>
              </li>
            </ul>

            <p
              v-else
              class="text-muted text-sm"
            >
              {{ $t('pipeline.lead.notes.empty') }}
            </p>
          </section>
        </div>
      </div>
    </article>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        title="pipeline.lost.title"
      >
        <MLostReasonForm
          v-if="pendingLost"
          :key="pendingLost.id"
          :pending="pendingLost"
          @onClose="dialogVisible = false"
          @onSuccess="onLostSaved"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
