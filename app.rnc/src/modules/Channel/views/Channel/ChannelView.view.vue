<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { ChannelBoard, ChannelFirm, ChannelStage } from '../../interfaces';
import { formatReal, translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { CHANNEL_ACTIVE_STAGE, CHANNEL_ERROR_CODES, CHANNEL_PERMISSIONS, CHANNEL_STAGES } from '../../constants';
import { AdvanceChannelFirmService, GetChannelService } from '../../services';

/*
 * "Canal RNC" (rnc-3 R8, R9, the demo's `rncView()`): the Rede Nacional de Contabilidade firms
 * Franco introduces to the platform. The hero is Franco's share of the active firms a month and a
 * year, and what the others add once they go live; then the count per stage and one row per firm
 * with its stepper. Every amount is "a conferir" — nothing is billed (D6).
 *
 * The operator (`channel.manage`) also sees the platform's fee, the lede that names it, the add, the
 * edit and "Avançar para <etapa>", which names the stage it showed (C3). Reinaldo (`channel.read`
 * only) reads the same board with no control and no platform price (AM15): the API sends neither
 * the fee nor the rate to him, and this screen never asks for them. Every answer is the whole board
 * (W41), so a move updates the hero and the counts without a second call; a stale move reloads it.
 */
interface AdvanceError {
  response?: { data?: { code?: string; error?: string }; status?: number };
  translatedMessage?: string;
}

const TOAST_LIFE_MS = 3500;
const STATUS_NOT_FOUND = 404;

const DATE_FORMAT = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  timeZone: 'America/Sao_Paulo',
  year: 'numeric',
});

const userStore = useUserStore();

const { locale, t } = useI18n();
const toast = useToast();

const advancingId = ref<number | null>(null);
const board = ref<ChannelBoard | null>(null);
const dialogVisible = ref(false);
const editingFirm = ref<ChannelFirm | null>(null);
const hasFailed = ref(false);
const isLoading = ref(true);

const activeCount = computed(() => board.value?.summary.counts[CHANNEL_ACTIVE_STAGE] ?? 0);
const canManage = computed(() => userStore.hasPermission(CHANNEL_PERMISSIONS.manage));
const dialogTitle = computed(() => (editingFirm.value ? 'channel.form.editHeading' : 'channel.form.addHeading'));

const lede = computed(() => {
  const defaults = board.value?.defaults;

  if (!canManage.value || !defaults) return t('channel.readLede');

  return t('channel.manageLede', {
    fee: formatReal(defaults.monthly_fee_cents),
    share: formatPercent(defaults.share_bp),
  });
});

const stageCounts = computed(() =>
  CHANNEL_STAGES.map((stage) => ({ count: board.value?.summary.counts[stage] ?? 0, stage }))
);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetChannelService()
    .then(({ data }) => (board.value = data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function formatPercent(basisPoints: number): string {
  return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(basisPoints / 100)}%`;
}

function isActive(firm: ChannelFirm): boolean {
  return firm.stage === CHANNEL_ACTIVE_STAGE;
}

function perMonth(cents: number): string {
  return t('channel.perMonth', { amount: formatReal(cents) });
}

function stageLabel(stage: ChannelStage): string {
  return t(`channel.stages.${stage}`);
}

function stepClass(firm: ChannelFirm, step: ChannelStage): string {
  const here = CHANNEL_STAGES.indexOf(firm.stage);
  const index = CHANNEL_STAGES.indexOf(step);

  if (index < here) return 'bg-primary-300/45';

  return index === here ? 'bg-primary-300' : 'bg-panel-muted ring-line ring-1 ring-inset';
}

// EVENTS
function onAdd() {
  editingFirm.value = null;
  dialogVisible.value = true;
}

function onAdvance(firm: ChannelFirm) {
  if (advancingId.value !== null || !firm.next_stage) return;

  const to = firm.next_stage;

  advancingId.value = firm.id;

  AdvanceChannelFirmService(firm.id, to)
    .then(({ data }) => onAdvanced(data, firm, to))
    .catch((failure: AdvanceError) => onAdvanceFailed(failure, firm))
    .finally(() => (advancingId.value = null));
}

function onAdvanced(data: ChannelBoard, firm: ChannelFirm, to: ChannelStage) {
  board.value = data;

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('channel.advanced', { name: firm.name, stage: stageLabel(to) }),
  });
}

function onAdvanceFailed(failure: AdvanceError, firm: ChannelFirm) {
  const data = failure.response?.data;

  if (data?.code === CHANNEL_ERROR_CODES.invalidTransition || failure.response?.status === STATUS_NOT_FOUND) {
    const summary =
      data?.code === CHANNEL_ERROR_CODES.invalidTransition
        ? t('channel.stale', { name: firm.name })
        : translateError(data?.error ?? 'channel.notFound');

    toast.add({ life: TOAST_LIFE_MS, severity: 'info', summary });
    load();
    return;
  }

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'error',
    summary: failure.translatedMessage ?? translateError('errors.unexpected'),
  });
}

function onEdit(firm: ChannelFirm) {
  editingFirm.value = firm;
  dialogVisible.value = true;
}

function onSaved(data: ChannelBoard, name: string) {
  const wasEditing = editingFirm.value !== null;

  dialogVisible.value = false;
  editingFirm.value = null;
  board.value = data;

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t(wasEditing ? 'channel.saved' : 'channel.added', { name }),
  });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="channel.title">
        <template
          v-if="canManage && board"
          #primaryAction
        >
          <Button
            data-testid="channel-add"
            icon="pi pi-plus"
            :label="$t('channel.add')"
            @click="onAdd"
          />
        </template>
      </ThePageHeader>
    </template>

    <div
      v-if="isLoading && !board"
      class="flex flex-col gap-3"
    >
      <Skeleton height="2.5rem" />

      <Skeleton height="11rem" />

      <Skeleton height="22rem" />
    </div>

    <div
      v-else-if="hasFailed && !board"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('channel.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('channel.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="board">
      <p
        class="text-body text-sm"
        data-testid="channel-lede"
      >
        {{ lede }}
      </p>

      <section
        data-testid="channel-hero"
        :class="[
          'motion-enter bg-panel shadow-lift grid min-w-0 grid-cols-1 gap-6 rounded-lg p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-end',
          { 'opacity-60 transition-opacity': isLoading },
        ]"
      >
        <div class="flex min-w-0 flex-col gap-2">
          <span class="text-muted text-xs font-semibold tracking-wide uppercase">{{ $t('channel.hero.label') }}</span>

          <strong
            class="motion-enter text-heading text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl"
            data-testid="channel-total"
            :key="board.summary.monthly_share_cents"
            :data-value="board.summary.monthly_share_cents"
          >
            {{ perMonth(board.summary.monthly_share_cents) }}
          </strong>

          <p class="text-body text-sm">
            {{
              $t(
                'channel.hero.base',
                { count: activeCount, yearly: formatReal(board.summary.yearly_share_cents) },
                activeCount
              )
            }}
            <template v-if="board.summary.pending_monthly_share_cents > 0">
              {{ $t('channel.hero.pending', { amount: formatReal(board.summary.pending_monthly_share_cents) }) }}
            </template>
            <template v-else>{{ $t('channel.hero.allActive') }}</template>
          </p>

          <span class="text-muted flex items-center gap-2 text-xs">
            <i
              aria-hidden="true"
              class="pi pi-info-circle text-subtle"
            />
            {{ $t('channel.toVerify') }}
          </span>
        </div>

        <ol
          class="grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-4"
          :aria-label="$t('channel.stagesLabel')"
        >
          <li
            v-for="item in stageCounts"
            data-testid="channel-stage"
            :key="item.stage"
            :class="[
              'bg-panel-muted flex min-w-0 flex-col gap-1 rounded-md p-3',
              { 'ring-primary-300 ring-1 ring-inset': item.stage === CHANNEL_ACTIVE_STAGE },
            ]"
            :data-count="item.count"
            :data-stage="item.stage"
          >
            <strong
              :class="[
                'text-2xl font-semibold tracking-tight tabular-nums',
                item.stage === CHANNEL_ACTIVE_STAGE ? 'text-brand-word' : 'text-heading',
              ]"
            >
              {{ item.count }}
            </strong>

            <span class="text-muted text-xs font-medium">{{ stageLabel(item.stage) }}</span>
          </li>
        </ol>
      </section>

      <section class="bg-panel shadow-lift flex min-w-0 flex-col rounded-lg">
        <header class="border-line flex flex-wrap items-baseline justify-between gap-2 border-b p-4 sm:px-5">
          <h2 class="text-heading text-lg font-semibold">{{ $t('channel.listTitle') }}</h2>

          <span class="text-muted text-xs">{{ $t('channel.toVerify') }}</span>
        </header>

        <AEmptyState
          v-if="board.firms.length === 0"
          icon="pi pi-share-alt"
          :description="canManage ? $t('channel.emptyManage') : $t('channel.emptyRead')"
          :title="$t('channel.emptyTitle')"
        />

        <ul
          v-else
          class="divide-line flex min-w-0 flex-col divide-y"
          data-testid="channel-firms"
        >
          <li
            v-for="(firm, index) in board.firms"
            class="motion-enter grid min-w-0 grid-cols-1 gap-x-8 gap-y-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-5"
            data-testid="channel-firm"
            :key="firm.id"
            :data-id="firm.id"
            :data-stage="firm.stage"
            :style="{ '--reveal-index': index + 1 }"
          >
            <div class="flex min-w-0 flex-col gap-1">
              <span class="text-heading font-semibold break-words">{{ firm.name }}</span>

              <span class="text-muted text-xs">
                {{ $t('channel.since', { city: firm.city, date: DATE_FORMAT.format(new Date(firm.stage_at)) }) }}
              </span>
            </div>

            <div class="flex min-w-0 flex-col gap-0.5 sm:items-end sm:text-right">
              <span class="text-muted text-xs tabular-nums">
                {{
                  canManage && firm.monthly_fee_cents !== undefined
                    ? perMonth(firm.monthly_fee_cents)
                    : $t('channel.shareLabel')
                }}
              </span>

              <strong
                data-testid="channel-share"
                :class="['tabular-nums', isActive(firm) ? 'text-heading font-semibold' : 'text-muted font-medium']"
              >
                {{ isActive(firm) ? perMonth(firm.share_cents) : $t('channel.onActivation') }}
              </strong>
            </div>

            <ol
              class="grid w-full max-w-md min-w-0 grid-cols-4 gap-1 self-center"
              :aria-label="$t('channel.stageAria', { stage: stageLabel(firm.stage) })"
            >
              <li
                v-for="step in CHANNEL_STAGES"
                class="flex min-w-0 flex-col gap-1.5"
                :key="step"
                :aria-current="step === firm.stage ? 'step' : undefined"
              >
                <span
                  aria-hidden="true"
                  :class="[
                    'motion-calm motion-ease-settle block h-1.5 rounded-full transition-colors',
                    stepClass(firm, step),
                  ]"
                />

                <span :class="['text-xs', step === firm.stage ? 'text-heading font-semibold' : 'text-muted']">
                  {{ stageLabel(step) }}
                </span>
              </li>
            </ol>

            <div class="flex min-w-0 flex-wrap items-center gap-2 sm:justify-end">
              <span
                v-if="isActive(firm)"
                class="bg-success-50 text-success-800 inline-flex items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-semibold"
                data-testid="channel-active"
              >
                <i
                  aria-hidden="true"
                  class="pi pi-check text-xs"
                />
                {{ stageLabel(CHANNEL_ACTIVE_STAGE) }}
              </span>

              <template v-if="canManage">
                <Button
                  v-if="firm.next_stage"
                  data-testid="channel-advance"
                  icon="pi pi-arrow-right"
                  iconPos="right"
                  size="small"
                  :disabled="advancingId !== null && advancingId !== firm.id"
                  :label="$t('channel.advance', { stage: stageLabel(firm.next_stage) })"
                  :loading="advancingId === firm.id"
                  @click="onAdvance(firm)"
                />

                <Button
                  data-testid="channel-edit"
                  icon="pi pi-pencil"
                  severity="secondary"
                  size="small"
                  :aria-label="`${$t('channel.edit')} — ${firm.name}`"
                  :label="$t('channel.edit')"
                  @click="onEdit(firm)"
                />
              </template>
            </div>
          </li>
        </ul>
      </section>
    </template>

    <aside class="contents">
      <MMainDialog
        v-if="canManage"
        v-model:visible="dialogVisible"
        isFooterless
        :title="dialogTitle"
      >
        <MAddEditChannelFirmForm
          v-if="dialogVisible && board?.defaults"
          :key="editingFirm?.id ?? 'new'"
          :defaults="board.defaults"
          :firm="editingFirm ?? undefined"
          @onClose="dialogVisible = false"
          @onSuccess="onSaved"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
