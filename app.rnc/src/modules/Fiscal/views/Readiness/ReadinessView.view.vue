<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import type { ReadinessDetail, ReadinessFilters, ReadinessList } from '../../interfaces';
import { vCountUp } from '@Directives';
import { useClientEvents, useFiscalFormat } from '../../composables';
import { DETAIL_BESIDE_QUERY, READINESS_BAR_STATUSES, READINESS_STATUSES } from '../../constants';
import { READINESS_STATUS } from '../../enums';
import { GetReadinessClientService, GetReadinessService } from '../../services';

/*
 * "Prontidão da NF-e" (rnc-2 R18–R20, the demo's `nfeView`): do the Presumido and Real clients'
 * issued invoices of the last 30 days already carry the IBS/CBS? The hero over EVERY listed client
 * whatever the filter (W76) — invoices at risk of rejection 1115, % complete, críticos, prontos and
 * the bar by status —, the status and system filters (each asks the API again), the list, one
 * client's issue lines with samples and its timeline, and "Avisar cliente". Every figure is the
 * API's; the screen only formats.
 */
const DETAIL_SCROLL_MARGIN = 16;

const BAR_CLASSES: Record<READINESS_STATUS, string> = {
  [READINESS_STATUS.ATENCAO]: 'bg-warn-500',
  [READINESS_STATUS.CRITICO]: 'bg-danger-600',
  [READINESS_STATUS.OK]: 'bg-success-600',
  [READINESS_STATUS.SEM_NOTAS]: 'bg-panel-muted',
};

const { events, failed: eventsFailed, load: loadEvents, loading: eventsLoading, prepend } = useClientEvents();
const { formatCount, formatPercent } = useFiscalFormat();

const detail = ref<ReadinessDetail | null>(null);
const detailFailed = ref(false);
const detailLoading = ref(false);
const filters = ref<ReadinessFilters>({ status: null, system: null });
const list = ref<ReadinessList | null>(null);
const loadFailed = ref(false);
const loading = ref(false);
const noticeVisible = ref(false);
const openId = ref<number | null>(null);

const systemOptions = computed(() => (list.value?.systems ?? []).map((system) => ({ label: system, value: system })));

const barSegments = computed(() =>
  READINESS_BAR_STATUSES.map((status) => ({ count: list.value?.summary.by_status[status] ?? 0, status })).filter(
    (segment) => segment.count > 0
  )
);

const withNotes = computed(() =>
  list.value ? list.value.summary.clients - list.value.summary.by_status.sem_notas : 0
);

onMounted(load);

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;

  GetReadinessService(filters.value)
    .then((response) => onLoaded(response.data))
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

/*
 * Scrolls TheLayout's own scroller, never `scrollIntoView`: that also scrolls `#app` and the
 * document, which lifted the whole layout off the top (Repricing's lesson).
 */
function scrollToDetail() {
  const target = document.querySelector('[data-testid=readiness-detail]');
  const scroller = target?.closest('.main-scroll');

  if (!target || !scroller) return;

  const offset = target.getBoundingClientRect().top - scroller.getBoundingClientRect().top - DETAIL_SCROLL_MARGIN;

  scroller.scrollBy?.({ behavior: 'smooth', top: offset });
}

// EVENTS
function onLoaded(data: ReadinessList) {
  list.value = data;

  const isStillListed = data.clients.some((client) => client.id === openId.value);

  if (isStillListed) return;

  openId.value = null;
  detail.value = null;
}

function onStatus(status: READINESS_STATUS | null) {
  if (status === filters.value.status) return;

  filters.value = { ...filters.value, status };
  load();
}

function onSystem(system: string | null) {
  filters.value = { ...filters.value, system: system ?? null };
  load();
}

function onOpen(id: number) {
  openId.value = id;
  detailLoading.value = true;
  detailFailed.value = false;

  GetReadinessClientService(id)
    .then((response) => (detail.value = response.data))
    .catch(() => (detailFailed.value = true))
    .finally(() => (detailLoading.value = false));

  loadEvents(id);

  if (window.matchMedia?.(DETAIL_BESIDE_QUERY).matches) return;

  nextTick(scrollToDetail);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="fiscal.readiness.title" />
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-3 rounded-lg p-5">
      <div class="flex flex-col gap-1.5">
        <h2 class="text-heading text-lg font-semibold">{{ $t('fiscal.readiness.heading') }}</h2>

        <p class="text-muted text-sm">{{ $t('fiscal.readiness.lede') }}</p>
      </div>

      <p class="bg-info-50 text-info-800 flex items-start gap-2 rounded-md px-3 py-2 text-sm">
        <i
          aria-hidden="true"
          class="pi pi-info-circle mt-0.5"
        />

        {{ $t('fiscal.readiness.note') }}
      </p>
    </section>

    <div
      v-if="loading && !list"
      class="flex flex-col gap-3"
    >
      <Skeleton height="12rem" />

      <Skeleton height="24rem" />
    </div>

    <div
      v-else-if="loadFailed && !list"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('fiscal.readiness.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('fiscal.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="list">
      <section
        class="bg-panel shadow-lift motion-enter grid min-w-0 grid-cols-1 gap-6 rounded-lg p-5 sm:p-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
        :style="{ '--reveal-index': 1 }"
      >
        <div class="flex min-w-0 flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="text-muted text-xs font-semibold tracking-wide uppercase">
              {{ $t('fiscal.readiness.hero.label') }}
            </span>

            <strong
              v-count-up
              class="text-heading text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl"
              data-testid="readiness-risk-total"
              :data-value="list.summary.at_risk"
            >
              {{ formatCount(list.summary.at_risk) }}
            </strong>

            <p class="text-muted text-sm">
              {{
                $t('fiscal.readiness.hero.base', {
                  clients: formatCount(withNotes),
                  invoices: formatCount(list.summary.invoices),
                })
              }}
            </p>
          </div>

          <ul class="grid grid-cols-3 gap-3">
            <li class="bg-panel-muted flex min-w-0 flex-col gap-1 rounded-md p-3">
              <span class="text-muted text-xs font-medium">{{ $t('fiscal.readiness.kpis.complete') }}</span>

              <strong
                class="text-heading text-xl font-semibold tabular-nums"
                data-testid="readiness-complete-pct"
              >
                {{ formatPercent(list.summary.complete_pct) }}
              </strong>
            </li>

            <li class="bg-panel-muted flex min-w-0 flex-col gap-1 rounded-md p-3">
              <span class="text-muted text-xs font-medium">{{ $t('fiscal.readiness.kpis.critical') }}</span>

              <strong
                class="text-heading text-xl font-semibold tabular-nums"
                data-testid="readiness-critical-count"
              >
                {{ formatCount(list.summary.by_status.critico) }}
              </strong>
            </li>

            <li class="bg-panel-muted flex min-w-0 flex-col gap-1 rounded-md p-3">
              <span class="text-muted text-xs font-medium">{{ $t('fiscal.readiness.kpis.ready') }}</span>

              <strong
                class="text-heading text-xl font-semibold tabular-nums"
                data-testid="readiness-ready-count"
              >
                {{ formatCount(list.summary.by_status.ok) }}
              </strong>
            </li>
          </ul>
        </div>

        <div class="border-line flex min-w-0 flex-col gap-3 border-t pt-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
          <span class="text-muted text-xs font-semibold tracking-wide uppercase">
            {{ $t('fiscal.readiness.hero.bar') }}
          </span>

          <div
            class="bg-panel-muted flex h-3 w-full gap-0.5 overflow-hidden rounded-sm"
            role="img"
            :aria-label="
              $t('fiscal.readiness.hero.barLabel', {
                atencao: list.summary.by_status.atencao,
                critico: list.summary.by_status.critico,
                ok: list.summary.by_status.ok,
              })
            "
          >
            <span
              v-for="segment in barSegments"
              data-testid="readiness-bar-segment"
              :key="segment.status"
              :class="['motion-wipe-y h-full', BAR_CLASSES[segment.status]]"
              :data-count="segment.count"
              :data-status="segment.status"
              :style="{ flexGrow: segment.count }"
            />
          </div>

          <ul class="flex flex-col gap-1.5">
            <li
              v-for="status in READINESS_BAR_STATUSES"
              class="text-body flex items-center gap-2 text-sm"
              :key="status"
            >
              <i
                aria-hidden="true"
                :class="['size-2.5 shrink-0 rounded-sm', BAR_CLASSES[status]]"
              />

              <span class="min-w-0 flex-1">{{ $t(`fiscal.statuses.${status}`) }}</span>

              <strong class="text-heading font-semibold tabular-nums">
                {{ formatCount(list.summary.by_status[status]) }}
              </strong>
            </li>
          </ul>

          <p
            v-if="list.summary.by_status.sem_notas > 0"
            class="text-muted text-xs"
          >
            {{ $t('fiscal.readiness.hero.noNotes', { count: formatCount(list.summary.by_status.sem_notas) }) }}
          </p>
        </div>
      </section>

      <section
        class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5 xl:flex-row xl:items-end xl:justify-between"
      >
        <div class="flex min-w-0 flex-col gap-2">
          <span
            id="readiness-status-label"
            class="text-muted mb-2 block text-sm"
          >
            {{ $t('fiscal.readiness.filters.status') }}
          </span>

          <div
            aria-labelledby="readiness-status-label"
            class="flex flex-wrap gap-3"
            role="group"
          >
            <Button
              data-testid="readiness-filter-all"
              size="small"
              :aria-pressed="filters.status === null ? 'true' : 'false'"
              :label="$t('fiscal.readiness.filters.all', { count: formatCount(list.summary.clients) })"
              :outlined="filters.status !== null"
              :severity="filters.status === null ? 'primary' : 'secondary'"
              @click="onStatus(null)"
            />

            <Button
              v-for="status in READINESS_STATUSES"
              :key="status"
              size="small"
              :aria-pressed="filters.status === status ? 'true' : 'false'"
              :data-testid="`readiness-filter-${status}`"
              :label="
                $t('fiscal.readiness.filters.option', {
                  count: formatCount(list.summary.by_status[status]),
                  label: $t(`fiscal.statuses.${status}`),
                })
              "
              :outlined="filters.status !== status"
              :severity="filters.status === status ? 'primary' : 'secondary'"
              @click="onStatus(status)"
            />
          </div>
        </div>

        <div class="flex w-full flex-col gap-2 xl:w-72">
          <label
            class="text-muted mb-2 block text-sm"
            for="readiness-system"
          >
            {{ $t('fiscal.readiness.filters.system') }}
          </label>

          <Select
            showClear
            class="w-full"
            inputId="readiness-system"
            optionLabel="label"
            optionValue="value"
            :modelValue="filters.system"
            :options="systemOptions"
            :placeholder="$t('fiscal.readiness.filters.allSystems')"
            @update:modelValue="onSystem"
          />
        </div>
      </section>

      <div class="grid min-w-0 grid-cols-1 items-start gap-4 2xl:grid-cols-[minmax(0,1fr)_26rem]">
        <section
          :class="[
            'bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-4 sm:p-5',
            { 'opacity-60 transition-opacity': loading },
          ]"
        >
          <div
            v-if="list.clients.length === 0"
            class="flex flex-col items-center gap-2 p-6 text-center"
            data-testid="readiness-empty"
          >
            <i
              aria-hidden="true"
              class="pi pi-filter-slash text-subtle text-2xl"
            />

            <p class="text-heading font-semibold">{{ $t('fiscal.readiness.table.emptyTitle') }}</p>

            <p class="text-muted text-sm">{{ $t('fiscal.readiness.table.emptyText') }}</p>
          </div>

          <MReadinessTable
            v-else
            :clients="list.clients"
            :openId
            @onOpen="onOpen"
          />
        </section>

        <MReadinessDetail
          class="2xl:sticky 2xl:top-4"
          :client="detail"
          :events
          :eventsFailed
          :eventsLoading
          :failed="detailFailed"
          :loading="detailLoading"
          @onNotify="noticeVisible = true"
        />
      </div>
    </template>

    <aside class="contents">
      <MMainDialog
        v-model:visible="noticeVisible"
        isFooterless
        title="fiscal.notice.title"
      >
        <MClientNoticeDialog
          v-if="detail"
          :key="detail.id"
          :client="detail"
          @onClose="noticeVisible = false"
          @onRegistered="prepend"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
