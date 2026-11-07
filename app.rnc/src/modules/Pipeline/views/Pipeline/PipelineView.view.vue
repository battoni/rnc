<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { LeadCard, LeadMove, PendingLostMove } from '../../interfaces';
import { getI18nRouteName, translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { useLeadBoard, useLeadFormat } from '../../composables';
import {
  LEAD_DRAG_TYPE,
  PIPELINE_PERMISSIONS,
  PIPELINE_ROUTE_NAMES,
  SEARCH_DEBOUNCE_MS,
  STAGE_ACCENT_CLASSES,
} from '../../constants';
import { LEAD_STAGE, MOVE_VIA } from '../../enums';
import { MoveLeadStageService } from '../../services';

/*
 * The team's pipeline (rnc-1 R18): one column per stage, a card per lead, moved by ◀ ▶ ✕ or by
 * dragging it to another column — each move a line on the lead's timeline with who and how.
 * Perdido asks for the reason first. The board scrolls sideways inside its own frame, never the page.
 */
interface MoveError {
  response?: { status?: number };
  translatedMessage?: string;
}

const HTTP_UNPROCESSABLE = 422;
const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const { t } = useI18n();
const toast = useToast();
const { stageLabel } = useLeadFormat();
const { appliedSearch, applyMove, columns, findCard, hasFailed, isLoading, load } = useLeadBoard();

const dialogVisible = ref(false);
const dropStage = ref<LEAD_STAGE | null>(null);
const movingId = ref<number | null>(null);
const pendingLost = ref<PendingLostMove | null>(null);
const search = ref('');

const canReadAgenda = computed(() => userStore.hasPermission(PIPELINE_PERMISSIONS.agenda));
const canUpdate = computed(() => userStore.hasPermission(PIPELINE_PERMISSIONS.update));
const isEmptySearch = computed(() => appliedSearch.value !== '' && columns.value.every((column) => column.count === 0));

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, onSearchInput);

onMounted(() => load(''));
onBeforeUnmount(() => searchTimer && clearTimeout(searchTimer));

// HELPERS
function moveCard(card: LeadCard, stage: LEAD_STAGE, via: MOVE_VIA) {
  if (card.stage === stage || movingId.value !== null) return;

  if (stage === LEAD_STAGE.PERDIDO) {
    pendingLost.value = { company: card.company, id: card.id, via };
    dialogVisible.value = true;
    return;
  }

  const payload = { stage, via };

  movingId.value = card.id;

  MoveLeadStageService(card.id, payload)
    .then((response) => onMoved(card.stage, response.data))
    .catch(onMoveFailed)
    .finally(() => (movingId.value = null));
}

function onMoved(from: LEAD_STAGE, move: LeadMove) {
  applyMove(move);

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('pipeline.moved', {
      company: move.lead.company ?? t('pipeline.anonymous'),
      from: stageLabel(from),
      to: stageLabel(move.lead.stage),
    }),
  });
}

function isLeadDrag(event: DragEvent): boolean {
  return Boolean(event.dataTransfer?.types.includes(LEAD_DRAG_TYPE));
}

// EVENTS
function onSearchInput(value: string) {
  if (searchTimer) clearTimeout(searchTimer);

  searchTimer = setTimeout(() => load(value.trim()), SEARCH_DEBOUNCE_MS);
}

function onRetry() {
  load(appliedSearch.value);
}

function onCardMove(card: LeadCard, stage: LEAD_STAGE) {
  moveCard(card, stage, MOVE_VIA.BUTTON);
}

function onColumnDragOver(event: DragEvent, stage: LEAD_STAGE) {
  if (!canUpdate.value || !isLeadDrag(event)) return;

  event.preventDefault();

  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';

  dropStage.value = stage;
}

function onColumnDragLeave(event: DragEvent, stage: LEAD_STAGE) {
  const stillInside =
    event.currentTarget instanceof Node &&
    event.relatedTarget instanceof Node &&
    event.currentTarget.contains(event.relatedTarget);

  if (stillInside || dropStage.value !== stage) return;

  dropStage.value = null;
}

function onColumnDrop(event: DragEvent, stage: LEAD_STAGE) {
  event.preventDefault();
  dropStage.value = null;

  const card = findCard(Number(event.dataTransfer?.getData(LEAD_DRAG_TYPE)));

  if (!card || !canUpdate.value) return;

  moveCard(card, stage, MOVE_VIA.DRAG);
}

function onMoveFailed(failure: MoveError) {
  const isRefusal = failure.response?.status === HTTP_UNPROCESSABLE;

  toast.add({
    life: TOAST_LIFE_MS,
    severity: isRefusal ? 'warn' : 'error',
    summary: failure.translatedMessage ?? translateError('errors.unexpected'),
  });
}

function onLostSaved(move: LeadMove) {
  const from = findCard(move.lead.id)?.stage ?? LEAD_STAGE.LEAD;

  dialogVisible.value = false;
  pendingLost.value = null;
  onMoved(from, move);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        collapseActions
        title="pipeline.title"
      >
        <template
          v-if="canReadAgenda && !dialogVisible"
          #actions
        >
          <RouterLink
            v-slot="{ navigate }"
            custom
            :to="{ name: getI18nRouteName(PIPELINE_ROUTE_NAMES.agenda) }"
          >
            <Button
              data-testid="open-agenda"
              icon="pi pi-calendar"
              severity="secondary"
              :label="$t('pipeline.openAgenda')"
              @click="navigate"
            />
          </RouterLink>
        </template>
      </ThePageHeader>
    </template>

    <div class="flex min-w-0 flex-col gap-5">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <p class="text-muted text-sm">{{ $t('pipeline.lede') }}</p>

        <IconField class="w-full shrink-0 lg:w-80">
          <InputIcon class="pi pi-search" />

          <InputText
            v-model="search"
            class="w-full"
            data-testid="pipeline-search"
            type="search"
            :aria-label="$t('pipeline.searchLabel')"
            :placeholder="$t('pipeline.searchPlaceholder')"
          />
        </IconField>
      </div>

      <div
        v-if="hasFailed"
        class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
        data-testid="pipeline-failed"
        role="alert"
      >
        <p class="text-body">{{ $t('pipeline.loadFailed') }}</p>

        <Button
          icon="pi pi-refresh"
          severity="secondary"
          :label="$t('pipeline.retry')"
          @click="onRetry"
        />
      </div>

      <template v-else>
        <p
          v-if="isEmptySearch"
          class="text-muted text-sm"
          data-testid="pipeline-empty-search"
          role="status"
        >
          {{ $t('pipeline.emptySearch', { search: appliedSearch }) }}
        </p>

        <!-- `w-0 min-w-full`: the board fills the column without lending it the seven columns' width —
             a plain block would push the layout's flex column (and the page) sideways at 1440. -->
        <div
          class="flex w-0 min-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pt-1 pb-4"
          data-testid="board"
          role="region"
          tabindex="0"
          :aria-busy="isLoading"
          :aria-label="$t('pipeline.boardLabel')"
        >
          <template v-if="isLoading && columns.length === 0">
            <Skeleton
              v-for="placeholder in 4"
              class="shrink-0"
              height="22rem"
              :key="placeholder"
              width="16rem"
            />
          </template>

          <section
            v-for="column in columns"
            data-testid="column"
            :key="column.stage"
            :aria-label="stageLabel(column.stage)"
            :class="[
              'bg-panel-muted flex w-[16.5rem] shrink-0 snap-start flex-col gap-3 rounded-lg p-3 transition-shadow sm:w-[17rem]',
              dropStage === column.stage ? 'ring-primary-400 ring-2' : '',
            ]"
            :data-stage="column.stage"
            @dragleave="onColumnDragLeave($event, column.stage)"
            @dragover="onColumnDragOver($event, column.stage)"
            @drop="onColumnDrop($event, column.stage)"
          >
            <header class="flex items-center gap-2 px-0.5">
              <span
                aria-hidden="true"
                :class="['size-2 shrink-0 rounded-full', STAGE_ACCENT_CLASSES[column.stage]]"
              />

              <h2 class="text-heading min-w-0 flex-1 truncate text-sm font-semibold">
                {{ stageLabel(column.stage) }}
              </h2>

              <span
                class="bg-panel text-body rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                data-testid="column-count"
              >
                {{ column.count }}
              </span>
            </header>

            <MLeadCard
              v-for="card in column.items"
              :key="card.id"
              :canUpdate
              :class="movingId === card.id ? 'pointer-events-none opacity-60' : ''"
              :lead="card"
              @onMove="onCardMove(card, $event)"
            />

            <p
              v-if="column.items.length === 0"
              class="text-muted border-line rounded-md border border-dashed px-3 py-6 text-center text-xs"
            >
              {{ $t('pipeline.emptyColumn') }}
            </p>

            <p
              v-if="column.count > column.items.length"
              class="text-muted px-0.5 text-xs"
            >
              {{ $t('pipeline.moreInColumn', { count: column.count - column.items.length }) }}
            </p>
          </section>
        </div>
      </template>
    </div>

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
