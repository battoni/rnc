<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type { Idea, IdeaFilters, IdeaPayload } from '../../interfaces';
import { getI18nRouteName } from '@Helpers';
import { useUserStore } from '@UserModule';
import useIdeaStatusWatch from '../../composables/useIdeaStatusWatch';
import { TOAST_ERROR_LIFE_MS, TOAST_SUCCESS_LIFE_MS } from '../../constants';
import { UnvoteIdeaService, UpdateIdeaService, VoteIdeaService } from '../../services';
import useIdeaStore from '../../store';

/**
 * The board — the product's feedback list, titled by the `ideas.title` key.
 *
 * The entry lives in the settings footer rather than the main navigation, because "idea" may
 * already be a word in the product built on this bootstrap. That is deliberate: the screen must
 * never be mistaken for the person's own ideas (see the module's RULES.md).
 *
 * Creating goes through the GLOBAL capture dialog, not a second one here. There is exactly one
 * place in the app that writes a new idea, so the shape of the form and the shape of its success
 * can never drift apart.
 */
const userStore = useUserStore();
const ideaStore = useIdeaStore();

const toast = useToast();
const { push } = useRouter();
const { t } = useI18n();

const { acknowledge, hasMoved, movedCount, rememberAll } = useIdeaStatusWatch();

const editingIdea = ref<Idea | null>(null);
const isDialogVisible = ref(false);
const isSaving = ref(false);
const votingId = ref<number | null>(null);

const canCreate = computed(() => userStore.hasPermission('ideas.create'));
const hasLoadError = computed(() => !ideaStore.isLoading && ideaStore.hasIdeasLoadError);
// Either dialog counts: the board's own edit, and the global capture opened from its CTA.
const isAnyDialogOpen = computed(() => isDialogVisible.value || ideaStore.isCaptureOpen);
const movedIdeasCount = computed(() => movedCount(ideaStore.ideas));

/*
 * A load that failed also leaves the list empty, and it is not an empty board: the empty states say
 * what to do next, the failure says the board did not load and offers to try again (celer-07).
 */
const hasEmptyResult = computed(
  () => !ideaStore.isLoading && !ideaStore.hasIdeasLoadError && ideaStore.ideas.length === 0
);
/*
 * An empty board and an empty RESULT are different things and must not share a message. Telling
 * somebody "nothing here yet" when their own filter is what emptied the screen is the interface
 * blaming the product for the person's last click.
 */
const isFiltered = computed(() => {
  const filters = ideaStore.filters;

  return Boolean(filters.status || filters.type || filters.area || filters.search) || filters.mine;
});

watch(() => ideaStore.filters, onFiltersChange, { deep: true });

onMounted(onViewMount);

// EVENTS

function onViewMount() {
  ideaStore.ensureIdeasLoaded().then(() => rememberAll(ideaStore.ideas));
}

function onFiltersChange() {
  ideaStore.loadIdeas().then(() => rememberAll(ideaStore.ideas));
}

function onRetryClick() {
  ideaStore.loadIdeas().then(() => rememberAll(ideaStore.ideas));
}

function onNewIdeaClick() {
  ideaStore.openCapture(null);
}

function onFilterChange(next: Partial<IdeaFilters>) {
  ideaStore.setFilters(next);
}

function onFiltersReset() {
  ideaStore.resetFilters();
}

/** Reading the idea is what acknowledges its change; nothing else clears the marker. */
function onOpenIdea(id: number) {
  const IDEA = ideaStore.ideas.find((item) => item.id === id);

  if (IDEA) acknowledge(IDEA);

  push({ name: getI18nRouteName('idea'), params: { id } });
}

function onEditClick(idea: Idea) {
  editingIdea.value = idea;
  isDialogVisible.value = true;
}

function onDialogClose() {
  isDialogVisible.value = false;
  editingIdea.value = null;
}

function onEditSubmit(payload: IdeaPayload) {
  if (!editingIdea.value) return;

  isSaving.value = true;

  UpdateIdeaService(editingIdea.value.id, payload)
    .then(({ data }) => {
      ideaStore.applyIdeaSaved(data);
      onDialogClose();

      toast.add({
        severity: 'success',
        summary: t('ideas.toast.savedTitle'),
        life: TOAST_SUCCESS_LIFE_MS,
      });
    })
    .catch((error) => {
      toast.add({
        severity: 'error',
        summary: t('ideas.toast.errorTitle'),
        detail: error?.translatedMessage ?? t('ideas.toast.errorDetail'),
        life: TOAST_ERROR_LIFE_MS,
      });
    })
    .finally(() => (isSaving.value = false));
}

/**
 * Support is NOT optimistic. The count is the board's ordering and its social signal, and a number
 * that jumps and then corrects itself is worse than one that waits 200ms — the endpoint answers
 * with the whole row, so the card lands on what the server actually holds.
 */
function onToggleVote(idea: Idea) {
  votingId.value = idea.id;

  const REQUEST = idea.voted ? UnvoteIdeaService(idea.id) : VoteIdeaService(idea.id);

  REQUEST.then(({ data }) => ideaStore.applyIdeaSaved(data))
    .catch((error) => {
      toast.add({
        severity: 'error',
        summary: t('ideas.toast.errorTitle'),
        detail: error?.translatedMessage ?? t('ideas.toast.errorDetail'),
        life: TOAST_ERROR_LIFE_MS,
      });
    })
    .finally(() => (votingId.value = null));
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        collapseActions
        icon="pi pi-lightbulb"
        title="ideas.title"
      >
        <template
          v-if="!isAnyDialogOpen"
          #primaryAction
        >
          <APageHeaderAction
            v-if="canCreate"
            data-testid="idea-new"
            icon="pi pi-plus"
            :label="$t('ideas.actions.new')"
            @click="onNewIdeaClick"
          />
        </template>
      </ThePageHeader>
    </template>

    <!-- No `min-h-0`. Inside TheLayout's scrolling flex column, `flex-1 min-h-0` shrinks this box
         BELOW its own content, and the excess overflows visibly WITHOUT joining the scrollable
         area — so the page cannot be scrolled to it and no bottom padding on the scroller can
         reach it. `min-h-0` belongs on a box that contains its own scroller; this screen scrolls
         the page. -->
    <div class="flex flex-1 flex-col gap-4">
      <!-- One plain sentence, no colour, no count in the navigation. -->
      <p
        v-if="movedIdeasCount > 0"
        class="text-muted flex items-center gap-2 text-sm"
        data-testid="idea-moved-summary"
      >
        <span class="bg-primary-500 inline-block size-2 shrink-0 rounded-xs" />

        {{ $t('ideas.moved', movedIdeasCount) }}
      </p>

      <MIdeaFilters
        :filters="ideaStore.filters"
        @onChange="onFilterChange"
        @onReset="onFiltersReset"
      />

      <div
        v-if="ideaStore.isLoading"
        class="flex flex-col gap-3"
      >
        <Skeleton height="6rem" />

        <Skeleton height="6rem" />

        <Skeleton height="6rem" />
      </div>

      <div
        v-if="hasLoadError"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="idea-load-error"
      >
        <i class="pi pi-exclamation-triangle text-warn-500 text-3xl" />

        <p class="text-heading text-base font-semibold">
          {{ $t('ideas.loadError.title') }}
        </p>

        <Button
          icon="pi pi-refresh"
          severity="secondary"
          :label="$t('ideas.loadError.retry')"
          @click="onRetryClick"
        />
      </div>

      <div
        v-if="hasEmptyResult && isFiltered"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="idea-empty-filtered"
      >
        <i class="pi pi-filter-slash text-subtle text-3xl" />

        <p class="text-heading text-base font-semibold">
          {{ $t('ideas.emptyFiltered.title') }}
        </p>

        <p class="text-muted max-w-md text-sm">
          {{ $t('ideas.emptyFiltered.description') }}
        </p>

        <Button
          severity="secondary"
          :label="$t('ideas.filters.clear')"
          @click="onFiltersReset"
        />
      </div>

      <div
        v-if="hasEmptyResult && !isFiltered"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="idea-empty"
      >
        <i class="pi pi-lightbulb text-subtle text-3xl" />

        <p class="text-heading text-base font-semibold">
          {{ $t('ideas.empty.title') }}
        </p>

        <p class="text-muted max-w-md text-sm">
          {{ $t('ideas.empty.description') }}
        </p>

        <Button
          v-if="canCreate"
          icon="pi pi-plus"
          :label="$t('ideas.actions.new')"
          @click="onNewIdeaClick"
        />
      </div>

      <div
        v-if="!ideaStore.isLoading && ideaStore.ideas.length > 0"
        class="flex flex-col gap-3"
      >
        <MIdeaCard
          v-for="idea in ideaStore.ideas"
          :key="idea.id"
          :hasMoved="hasMoved(idea)"
          :idea
          :isVoting="votingId === idea.id"
          @onEdit="onEditClick"
          @onOpen="onOpenIdea"
          @onToggleVote="onToggleVote"
        />
      </div>
    </div>

    <aside class="contents">
      <MMainDialog
        v-model:visible="isDialogVisible"
        isFooterless
        title="ideas.actions.edit"
        @onClose="onDialogClose"
      >
        <MIdeaForm
          :key="editingIdea?.id ?? 'new'"
          :idea="editingIdea"
          :isSaving
          @onClose="onDialogClose"
          @onSubmit="onEditSubmit"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
