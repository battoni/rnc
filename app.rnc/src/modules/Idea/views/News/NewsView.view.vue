<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { NEWS_REACTION } from '../../enums';
import type { Idea, NewsEntry, NewsPayload, NewsReader } from '../../interfaces';
import type { NewsReadFilter } from '../../types';
import { HTTP_STATUS } from '@Enums';
import { toRouteId } from '@Helpers';
import { useUserStore } from '@UserModule';
import useIdeaFormat from '../../composables/useIdeaFormat';
import { TOAST_ERROR_LIFE_MS, TOAST_SUCCESS_LIFE_MS } from '../../constants';
import { IDEA_STATUS, NEWS_READ_FILTER } from '../../enums';
import {
  CreateNewsService,
  DeleteNewsService,
  GetIdeasService,
  GetNewsReadersService,
  MarkNewsReadService,
  PublishNewsService,
  ReactToNewsService,
  UpdateNewsService,
} from '../../services';
import useIdeaStore from '../../store';

/**
 * Novidades — the product's changelog, written to be read rather than skimmed.
 *
 * The list is covers, titles and standfirsts; the article opens in a dialog. That split is the
 * point: a page of full articles is a page nobody finishes, and a list of bare one-liners is a page
 * nobody opens.
 *
 * It is fed by its OWN table rather than by delivered ideas, which is the honest shape — most of
 * what ships was never proposed on the board. When an entry does answer an idea, it credits its
 * author by name, and that is the whole reward loop the Ideias board is built around.
 */
const userStore = useUserStore();
const ideaStore = useIdeaStore();

const confirm = useConfirm();
const route = useRoute();
const { replace } = useRouter();
const toast = useToast();
const { t } = useI18n();

const { formatMonth, monthKey } = useIdeaFormat();

/*
 * After a reaction 429 the buttons pause briefly so a hammering thumb does not open a toast per
 * click. Cleared on a short timer — applause itself stays unlimited once the window resets.
 */
const REACTIONS_COOLDOWN_MS = 3000;
/*
 * The query key a share link carries. Portuguese, for the same reason the API's half of the link
 * is `/n/` and not `/share/news/`: it is typed into WhatsApp and read by people.
 */
const SHARED_ENTRY_QUERY_KEY = 'entrada';
let reactionsCooldownTimer: ReturnType<typeof setTimeout> | null = null;

const areReactionsCoolingDown = ref(false);
const deliveredIdeas = ref<Idea[]>([]);
const editingEntry = ref<NewsEntry | null>(null);
const isDialogVisible = ref(false);
const isReadersLoading = ref(false);
const isSaving = ref(false);
const publishingId = ref<number | null>(null);
const readers = ref<NewsReader[]>([]);
const readersEntry = ref<NewsEntry | null>(null);
const readingEntry = ref<NewsEntry | null>(null);
const readFilter = ref<NewsReadFilter>(NEWS_READ_FILTER.ALL);

// Writing the changelog is its own key now; moderating the board is a different job.

const canManage = computed(() => userStore.hasPermission('news.manage'));
const hasLoadError = computed(() => !ideaStore.isNewsLoading && ideaStore.hasNewsLoadError);
const hasEntriesToFilter = computed(() => !hasNoEntries.value && !hasLoadError.value && !ideaStore.isNewsLoading);
const isArticleOpen = computed({ get: () => readingEntry.value !== null, set: (open) => !open && onArticleClose() });
const isReadersOpen = computed({ get: () => readersEntry.value !== null, set: (open) => !open && onReadersClose() });

const hasNoEntries = computed(
  () => !ideaStore.isNewsLoading && !ideaStore.hasNewsLoadError && ideaStore.newsEntries.length === 0
);
/*
 * A filter that empties the page must say so. Telling someone “nothing published yet” when their
 * own filter hid everything is the bug the Ideas board already shipped once, and it teaches people
 * that the empty state lies. A failed load empties the list too, and that is not the filter's doing.
 */
const hasNothingForFilter = computed(
  () => !ideaStore.isNewsLoading && !hasLoadError.value && !hasNoEntries.value && filteredEntries.value.length === 0
);

const deliveredIdeaOptions = computed(() =>
  deliveredIdeas.value.map((idea) => ({ label: idea.title, value: idea.id }))
);

const readFilterOptions = computed(() => [
  { label: t('news.filter.all'), value: NEWS_READ_FILTER.ALL },
  { label: t('news.filter.unread'), value: NEWS_READ_FILTER.UNREAD },
  { label: t('news.filter.read'), value: NEWS_READ_FILTER.READ },
]);

/**
 * A draft belongs to neither side.
 *
 * Only a maintainer sees drafts, and a draft has no audience yet — it cannot have been read, and
 * calling it “unread” would file the writer's own unfinished work under the reader's backlog. It
 * shows under “Todas” and nowhere else.
 */
const filteredEntries = computed(() => {
  if (readFilter.value === NEWS_READ_FILTER.ALL) return ideaStore.newsEntries;

  const WANTS_READ = readFilter.value === NEWS_READ_FILTER.READ;

  return ideaStore.newsEntries.filter((entry) => entry.published && entry.read === WANTS_READ);
});

/** Grouped by a `YYYY-MM` key on the reader's clock, never by the localised label, which changes with the locale. */
const groupedEntries = computed(() => {
  const GROUPS = new Map<string, NewsEntry[]>();

  filteredEntries.value.forEach((entry) => {
    const KEY = monthKey(entry.publishedAt ?? entry.createdAt);
    GROUPS.set(KEY, [...(GROUPS.get(KEY) ?? []), entry]);
  });

  return Array.from(GROUPS.entries()).map(([key, entries]) => ({
    key,
    label: formatMonth(entries[0]?.publishedAt ?? entries[0]?.createdAt ?? null),
    entries,
  }));
});

onMounted(onViewMount);
onBeforeUnmount(onViewUnmount);

// HELPERS

function showError(error: unknown) {
  toast.add({
    severity: 'error',
    summary: t('ideas.toast.errorTitle'),
    detail: (error as { translatedMessage?: string })?.translatedMessage ?? t('ideas.toast.errorDetail'),
    life: TOAST_ERROR_LIFE_MS,
  });
}

function startReactionsCooldown() {
  areReactionsCoolingDown.value = true;

  if (reactionsCooldownTimer) clearTimeout(reactionsCooldownTimer);

  reactionsCooldownTimer = setTimeout(() => {
    areReactionsCoolingDown.value = false;
    reactionsCooldownTimer = null;
  }, REACTIONS_COOLDOWN_MS);
}

// EVENTS

function onViewUnmount() {
  if (reactionsCooldownTimer) clearTimeout(reactionsCooldownTimer);
}

function onShowAllClick() {
  readFilter.value = NEWS_READ_FILTER.ALL;
}

function onRetryClick() {
  ideaStore.loadNews();
}

function onViewMount() {
  ideaStore.loadNews().then(openSharedEntry);

  // Only somebody who can credit an idea needs the list of them.
  if (canManage.value) loadDeliveredIdeas();
}

/*
 * A shared link names its entry, and the article opens on arrival.
 *
 * After the list rather than before it: the article is read out of the list, so there is nothing
 * to open until it lands. Someone signed out still gets here — the auth guard carries the whole
 * path, query included, through the login screen and back.
 *
 * An id that names nothing is reported and then stripped from the URL. The link may be old and the
 * entry may be gone; saying so is clearer than leaving a query that opens nothing.
 *
 * Only when the list actually arrived, though. `loadNews` swallows its own failure and resolves,
 * so a network blip lands here with an empty list and every id looks missing — the screen already
 * says "could not load" and a second, wronger sentence on top of it helps nobody. Worse, stripping
 * the query then destroys the shared link, so the Retry button reloads the list and opens nothing.
 */
function openSharedEntry() {
  const REQUESTED = route.query[SHARED_ENTRY_QUERY_KEY];
  const RAW_ID = Array.isArray(REQUESTED) ? REQUESTED[0] : REQUESTED;

  if (!RAW_ID) return;

  const ID = toRouteId(RAW_ID);

  if (ideaStore.hasNewsLoadError) return;

  const ENTRY = ideaStore.newsEntries.find((entry) => entry.id === ID);

  if (ENTRY) {
    onReadClick(ENTRY);
    return;
  }

  toast.add({
    severity: 'error',
    summary: t('ideas.toast.errorTitle'),
    detail: t('news.toast.notFound'),
    life: TOAST_ERROR_LIFE_MS,
  });

  clearSharedEntryQuery();
}

function clearSharedEntryQuery() {
  if (!route.query[SHARED_ENTRY_QUERY_KEY]) return;

  const QUERY = { ...route.query };
  delete QUERY[SHARED_ENTRY_QUERY_KEY];

  replace({ query: QUERY });
}

function loadDeliveredIdeas() {
  GetIdeasService({ status: IDEA_STATUS.DELIVERED })
    .then(({ data }) => (deliveredIdeas.value = data))
    .catch(showError);
}

function onNewClick() {
  editingEntry.value = null;
  isDialogVisible.value = true;
}

function onEditClick(entry: NewsEntry) {
  editingEntry.value = entry;
  isDialogVisible.value = true;
}

function onDialogClose() {
  isDialogVisible.value = false;
  editingEntry.value = null;
}

function onSubmit(payload: NewsPayload) {
  isSaving.value = true;

  const isEditing = editingEntry.value !== null;
  const REQUEST = isEditing ? UpdateNewsService(editingEntry.value!.id, payload) : CreateNewsService(payload);

  REQUEST.then(({ data }) => {
    onDialogClose();

    // The API answers with the saved entry, so the list is patched rather than refetched.
    if (isEditing) ideaStore.replaceNewsEntry(data);
    if (!isEditing) ideaStore.addNewsEntry(data);

    toast.add({ severity: 'success', summary: t('ideas.toast.savedTitle'), life: TOAST_SUCCESS_LIFE_MS });
  })
    .catch(showError)
    .finally(() => (isSaving.value = false));
}

/*
 * Deleting takes the cover off the disk and the reads and applause with it, through the cascades.
 * A mis-aimed click beside the pencil would destroy a published article and its whole history, so
 * it asks first — as every other destructive action in this app does.
 */
function onDeleteClick(target: HTMLElement, entry: NewsEntry) {
  confirm.require({
    target,
    group: 'news-delete',
    message: t('news.actions.deleteConfirm'),
    acceptProps: { label: t('common.actions.delete'), severity: 'danger', size: 'small' },
    rejectProps: { label: t('common.actions.cancel'), severity: 'secondary', size: 'small', text: true },
    accept: () => onDeleteConfirm(entry),
  });
}

function onDeleteConfirm(entry: NewsEntry) {
  DeleteNewsService(entry.id)
    .then(({ data }) => ideaStore.removeNewsEntry(data.id))
    .catch(showError);
}

/*
 * Publishing is one click from the card, not a trip through the form. Writing and announcing are
 * different moments — the whole reason a draft state exists — and the moment you announce, you are
 * re-reading the card, not editing it.
 */
function onPublishClick(entry: NewsEntry) {
  publishingId.value = entry.id;

  PublishNewsService(entry.id, !entry.published)
    .then(({ data }) => ideaStore.replaceNewsEntry(data))
    .catch(showError)
    .finally(() => (publishingId.value = null));
}

/*
 * Opening the article is what marks it read — not scrolling past the card. The list is patched with
 * whatever comes back, so the read flag and the reaction counts on the card stay true without a
 * refetch, and the nav dot is decremented locally rather than re-asked.
 */
function onReadClick(entry: NewsEntry) {
  readingEntry.value = entry;

  if (!entry.published) return;

  MarkNewsReadService(entry.id)
    .then(({ data }) => {
      // After the server has it, never before: a dot that clears on a request that failed is a
      // dot claiming something was read when the server still says it was not.
      ideaStore.noteNewsRead(entry);
      ideaStore.replaceNewsEntry(data);

      // The open dialog holds its own copy; without this the applause it shows is one click stale.
      if (readingEntry.value?.id === data.id) readingEntry.value = data;
    })
    .catch(showError);
}

/*
 * Applause, from the card or from the open article — the same request either way, and the same
 * entry patched into the list, so a clap given on the card is already counted when the article is
 * opened a second later.
 */
function onReactClick(type: NEWS_REACTION, settle: () => void, entry: NewsEntry) {
  ReactToNewsService(entry.id, type)
    .then(({ data }) => {
      ideaStore.replaceNewsEntry(data);

      if (readingEntry.value?.id === data.id) readingEntry.value = data;
    })
    .catch((error: unknown) => {
      const status =
        (error as { response?: { status?: number }; status?: number })?.response?.status ??
        (error as { status?: number })?.status;

      if (status === HTTP_STATUS.TOO_MANY_REQUESTS) startReactionsCooldown();

      showError(error);
    })
    /*
     * `settle` repays THIS click's optimistic count, whether the request succeeded or failed. It
     * is handed to us by MNewsReactions with the emit, so the component does not have to guess
     * from a prop changing — which it used to, and which meant any unrelated write to the entry
     * (marking it read, publishing it) repaid a clap that was still in flight.
     *
     * Nothing is restored on failure: `entry` here is the entry as it was AT CLICK TIME, and with
     * two claps in flight the second one's failure would have written the first one's success back
     * out of the store. Repaying the claim is enough — the counts on screen return to whatever the
     * store actually holds.
     */
    .finally(settle);
}

function onReadersClick(entry: NewsEntry) {
  readersEntry.value = entry;
  readers.value = [];
  isReadersLoading.value = true;

  GetNewsReadersService(entry.id)
    .then(({ data }) => (readers.value = data))
    .catch(showError)
    .finally(() => (isReadersLoading.value = false));
}

function onReadersClose() {
  readersEntry.value = null;
  readers.value = [];
}

function onArticleClose() {
  readingEntry.value = null;

  // The URL stops naming an entry that is no longer open, so a reload does not reopen what was
  // just closed and the address bar goes on describing the screen.
  clearSharedEntryQuery();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        collapseActions
        icon="pi pi-megaphone"
        title="news.title"
      >
        <template
          v-if="!isDialogVisible"
          #primaryAction
        >
          <APageHeaderAction
            v-if="canManage"
            data-testid="news-new"
            icon="pi pi-plus"
            :label="$t('news.actions.new')"
            @click="onNewClick"
          />
        </template>

        <!-- Secondary to "Nova entrada", and for a reader without `news.manage` the only action
             the page has — which is the point of moving it up here. Left over the list it was a
             control floating above the first month heading, reading as part of the list rather
             than as something to press, while the header sat half empty beside the CTA.

             The `v-if` carries BOTH conditions on purpose. `!isDialogVisible` matches the primary
             action — the header's actions step aside while a dialog is open. The rest is the old
             condition, unchanged: a filter over nothing is furniture. Both belong on the
             `<template>` and not on the `SelectButton`, because `ThePageHeader` decides whether to
             draw the phone's `⋮` from whether this slot EXISTS — a slot that renders nothing would
             still buy an overflow button opening an empty panel. -->
        <template
          v-if="!isDialogVisible && hasEntriesToFilter"
          #actions
        >
          <SelectButton
            v-model="readFilter"
            data-testid="news-read-filter"
            optionLabel="label"
            optionValue="value"
            size="small"
            :allowEmpty="false"
            :options="readFilterOptions"
          />
        </template>
      </ThePageHeader>
    </template>

    <div class="flex flex-1 flex-col gap-6">
      <Skeleton
        v-if="ideaStore.isNewsLoading"
        height="10rem"
      />

      <!-- A failed load and an empty changelog are different facts, and only one of them is worth
           retrying. -->
      <div
        v-if="hasLoadError"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="news-load-error"
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
        v-if="hasNothingForFilter"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="news-empty-filter"
      >
        <i class="pi pi-filter-slash text-subtle text-3xl" />

        <p class="text-heading text-base font-semibold">
          {{ $t('news.filter.emptyTitle') }}
        </p>

        <Button
          severity="secondary"
          size="small"
          :label="$t('news.filter.showAll')"
          @click="onShowAllClick"
        />
      </div>

      <div
        v-if="hasNoEntries"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="news-empty"
      >
        <i class="pi pi-megaphone text-subtle text-3xl" />

        <p class="text-heading text-base font-semibold">
          {{ $t('news.empty.title') }}
        </p>

        <p class="text-muted max-w-md text-sm">
          {{ $t('news.empty.description') }}
        </p>
      </div>

      <section
        v-for="group in groupedEntries"
        class="@container flex flex-col gap-3"
        :key="group.key"
      >
        <h2 class="text-muted text-xs font-semibold tracking-wide uppercase">
          {{ group.label }}
        </h2>

        <!-- The steps are CONTAINER widths, not viewport widths, and that is the whole point: the
             content column shrinks by 248px when the navigation rail is open, so the same monitor
             has room for four cards or five depending on something the viewport cannot see. A
             media query answers the window; this answers the space the cards actually get.

             The numbers are measured, not chosen. Below 310px the card's footer wraps — the three
             applause buttons on one line and the read count on another — so each step is the width
             at which one more 320px card fits beside the others with the 16px gap between them:
             660, 1000, 1340, 1680.

             A grid, not a stack. One entry per row read as a wall of covers you scrolled past
             rather than a changelog you scanned; two columns fit the whole month on one screen.
             One column on a phone, where a half-width cover is a thumbnail nobody can read.

             `items-start`, so a card is as tall as what it holds. Stretched to the row's height it
             aligned the footers and paid for it with a 180px hole inside every entry that has no
             cover — and most entries have no cover. -->
        <div
          class="grid grid-cols-1 items-start gap-4 @min-[660px]:grid-cols-2 @min-[1000px]:grid-cols-3 @min-[1340px]:grid-cols-4 @min-[1680px]:grid-cols-5"
        >
          <MNewsCard
            v-for="entry in group.entries"
            :key="entry.id"
            :canManage
            :entry
            :isPublishing="publishingId === entry.id"
            :reactionsDisabled="areReactionsCoolingDown"
            @onDelete="onDeleteClick($event, entry)"
            @onEdit="onEditClick(entry)"
            @onOpen="onReadClick(entry)"
            @onPublish="onPublishClick(entry)"
            @onReact="(type: NEWS_REACTION, settle: () => void) => onReactClick(type, settle, entry)"
            @onReaders="onReadersClick(entry)"
          />
        </div>
      </section>
    </div>

    <aside class="contents">
      <ConfirmPopup group="news-delete" />

      <MMainDialog
        v-model:visible="isDialogVisible"
        isFooterless
        :title="editingEntry ? 'news.actions.edit' : 'news.actions.new'"
        @onClose="onDialogClose"
      >
        <MNewsForm
          :key="editingEntry?.id ?? 'new'"
          :deliveredIdeaOptions
          :entry="editingEntry"
          :isSaving
          @onClose="onDialogClose"
          @onSubmit="onSubmit"
        />
      </MMainDialog>

      <MMainDialog
        v-model:visible="isReadersOpen"
        closeOnEscape
        isFooterless
        title="news.readers.title"
        @onClose="onReadersClose"
      >
        <MNewsReaders
          :isLoading="isReadersLoading"
          :readers
        />
      </MMainDialog>

      <MMainDialog
        v-model:visible="isArticleOpen"
        closeOnEscape
        isFooterless
        title="news.title"
        @onClose="onArticleClose"
      >
        <MNewsArticle
          v-if="readingEntry"
          :entry="readingEntry"
          :reactionsDisabled="areReactionsCoolingDown"
          @onReact="(type: NEWS_REACTION, settle: () => void) => onReactClick(type, settle, readingEntry!)"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
