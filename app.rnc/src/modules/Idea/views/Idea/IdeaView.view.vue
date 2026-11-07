<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { Idea, IdeaComment, IdeaStatusPayload } from '../../interfaces';
import { HTTP_STATUS } from '@Enums';
import { getI18nRouteName, toRouteId } from '@Helpers';
import { useUserStore } from '@UserModule';
import useIdeaFormat from '../../composables/useIdeaFormat';
import { IDEA_COMMENT_MAX_LENGTH, TOAST_ERROR_LIFE_MS, TOAST_SUCCESS_LIFE_MS } from '../../constants';
import { IDEA_STATUS } from '../../enums';
import { toHtmlParagraph } from '../../helpers';
import {
  CreateIdeaCommentService,
  CreateNewsService,
  DeleteIdeaCommentService,
  GetIdeaService,
  GetIdeasService,
  HideIdeaService,
  MergeIdeaService,
  UnvoteIdeaService,
  UpdateIdeaStatusService,
  VoteIdeaService,
} from '../../services';
import useIdeaStore from '../../store';

/**
 * One idea, in full: the text, the screenshot, the maintainer's answer, and the conversation.
 *
 * The maintainer's controls are on this screen and not on the board, because triage is a decision
 * made after reading the whole thing — a status set from a two-line card preview is a status set
 * without the argument.
 */
const userStore = useUserStore();
const ideaStore = useIdeaStore();

const toast = useToast();
const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();

const { formatMoment } = useIdeaFormat();

const commentBody = ref('');
const comments = ref<IdeaComment[]>([]);
const idea = ref<Idea | null>(null);
const isCommenting = ref(false);
const isLoading = ref(true);
const isMergeVisible = ref(false);
const isSavingStatus = ref(false);
const isVoting = ref(false);
const mergeCandidates = ref<Idea[]>([]);
const mergeTargetId = ref<number | null>(null);

const announceBody = ref('');
const announceTitle = ref('');
const isAnnouncing = ref(false);
const isAnnounceVisible = ref(false);

const canComment = computed(() => userStore.hasPermission('ideas.read'));
const canModerate = computed(() => userStore.hasPermission('ideas.manage'));
const ideaId = computed(() => toRouteId(route.params.id));
const isAnnounceValid = computed(() => announceTitle.value.trim().length > 0 && announceBody.value.trim().length > 0);
const isCommentValid = computed(() => commentBody.value.trim().length > 0);

/*
 * Fetched unfiltered when the dialog opens, NOT read from the board.
 *
 * `ideaStore.ideas` is whatever the last filter left behind, so a maintainer who had narrowed the
 * board to "planejada" would be offered only planned ideas as merge targets — with nothing on
 * screen explaining why the obvious duplicate is missing.
 */
const mergeOptions = computed(() =>
  mergeCandidates.value
    // Hidden or already merged, the API refuses the target (`ideas.merge.targetUnavailable`).
    .filter((item) => item.id !== ideaId.value && !item.hidden && !item.mergedIntoId)
    .map((item) => ({ label: item.title, value: item.id }))
);

onMounted(onViewMount);

// HELPERS

/** A comment the sender may remove: their own, or anyone's when they run the board. */
function canDeleteComment(comment: IdeaComment): boolean {
  return canModerate.value || comment.author?.id === userStore.user?.id;
}

function showError(error: unknown) {
  toast.add({
    severity: 'error',
    summary: t('ideas.toast.errorTitle'),
    detail: (error as { translatedMessage?: string })?.translatedMessage ?? t('ideas.toast.errorDetail'),
    life: TOAST_ERROR_LIFE_MS,
  });
}

// EVENTS

function onViewMount() {
  loadIdea();
}

/**
 * A bad id is a toast and a redirect to the board — never a screen wired to nothing — but ONLY a
 * bad id. A network blip or a 500 used to send the reader back too, losing their place over
 * something that a reload would have fixed. The toast names what happened so the empty board does
 * not look like a silent failure.
 */
function loadIdea() {
  isLoading.value = true;

  GetIdeaService(ideaId.value)
    .then(({ data }) => {
      idea.value = data;
      comments.value = data.comments ?? [];
    })
    .catch((error) => {
      const IS_MISSING = (error as { response?: { status?: number } })?.response?.status === HTTP_STATUS.NOT_FOUND;

      if (IS_MISSING) {
        toast.add({
          severity: 'error',
          summary: t('ideas.toast.errorTitle'),
          detail: t('ideas.toast.notFound'),
          life: TOAST_ERROR_LIFE_MS,
        });
        push({ name: getI18nRouteName('ideas') });
        return;
      }

      showError(error);
    })
    .finally(() => (isLoading.value = false));
}

function onBackClick() {
  push({ name: getI18nRouteName('ideas') });
}

function onToggleVote() {
  if (!idea.value) return;

  isVoting.value = true;

  const REQUEST = idea.value.voted ? UnvoteIdeaService(idea.value.id) : VoteIdeaService(idea.value.id);

  REQUEST.then(({ data }) => {
    idea.value = { ...idea.value, ...data };
    ideaStore.applyIdeaSaved(data);
  })
    .catch(showError)
    .finally(() => (isVoting.value = false));
}

function onStatusSubmit(payload: IdeaStatusPayload) {
  if (!idea.value) return;

  isSavingStatus.value = true;

  UpdateIdeaStatusService(idea.value.id, payload)
    .then(({ data }) => {
      idea.value = { ...idea.value, ...data };
      ideaStore.applyIdeaSaved(data);

      toast.add({
        severity: 'success',
        summary: t('ideas.toast.statusTitle'),
        life: TOAST_SUCCESS_LIFE_MS,
      });

      if (data.status === IDEA_STATUS.DELIVERED) openAnnounce(data);
    })
    .catch(showError)
    .finally(() => (isSavingStatus.value = false));
}

/**
 * Marking an idea delivered offers to announce it, pre-filled from the idea itself.
 *
 * This is what keeps Novidades from being a second job. The changelog is its own table precisely
 * because most entries answer no idea — but the ones that DO are exactly the entries somebody is
 * waiting for, and asking the maintainer to retype them elsewhere is how they stop being written.
 * It is an offer, never automatic: an entry nobody chose to publish is not an announcement.
 */
function openAnnounce(idea: Idea) {
  announceTitle.value = idea.title;
  announceBody.value = idea.adminNote ?? '';
  isAnnounceVisible.value = true;
}

function onAnnounceClose() {
  isAnnounceVisible.value = false;
}

function onAnnounceSubmit() {
  if (!idea.value || !isAnnounceValid.value) return;

  isAnnouncing.value = true;

  CreateNewsService({
    title: announceTitle.value.trim(),
    subtitle: null,
    // The entry inherits the idea's area. They are the same vocabulary and the same subject —
    // asking the writer to pick it again is asking them to disagree with themselves.
    areas: idea.value.area ? [idea.value.area] : [],
    // Wrapped as a paragraph because the body is HTML now; a bare line would render unstyled.
    // Escaped on the way in — see `toHtmlParagraph`, and the "a < b" it exists for.
    body: toHtmlParagraph(announceBody.value),
    link: null,
    ideaId: idea.value.id,
    publish: true,
  })
    .then(() => {
      onAnnounceClose();

      toast.add({
        severity: 'success',
        summary: t('news.toast.publishedTitle'),
        detail: t('news.toast.publishedDetail'),
        life: TOAST_SUCCESS_LIFE_MS,
      });
    })
    .catch(showError)
    .finally(() => (isAnnouncing.value = false));
}

function onHideToggle() {
  if (!idea.value) return;

  HideIdeaService(idea.value.id)
    .then(({ data }) => {
      idea.value = { ...idea.value, ...data };
      ideaStore.applyIdeaSaved(data);
    })
    .catch(showError);
}

function onMergeOpen() {
  mergeTargetId.value = null;
  isMergeVisible.value = true;

  GetIdeasService({})
    .then(({ data }) => (mergeCandidates.value = data))
    .catch(showError);
}

function onMergeClose() {
  isMergeVisible.value = false;
}

/** After a merge this idea is a duplicate and no longer belongs on screen. */
function onMergeConfirm() {
  if (!idea.value || !mergeTargetId.value) return;

  MergeIdeaService(idea.value.id, mergeTargetId.value)
    .then(({ data }) => {
      ideaStore.applyIdeaSaved(data);
      onMergeClose();
      push({ name: getI18nRouteName('idea'), params: { id: data.id } }).then(loadIdea);
    })
    .catch(onMergeRefused);
}

/** A 422 names the rule the merge broke: a warning in the person's language, not the red error. */
function onMergeRefused(error: { response?: { status?: number }; translatedMessage?: string }) {
  const isRefusal = error?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;

  if (!isRefusal) {
    showError(error);
    return;
  }

  toast.add({
    severity: 'warn',
    summary: error.translatedMessage ?? t('ideas.toast.errorDetail'),
    life: TOAST_ERROR_LIFE_MS,
  });
}

function onCommentSubmit() {
  if (!idea.value || !isCommentValid.value) return;

  isCommenting.value = true;

  CreateIdeaCommentService(idea.value.id, commentBody.value.trim())
    .then(({ data }) => {
      comments.value = [...comments.value, data];
      commentBody.value = '';

      if (idea.value) idea.value.commentsCount += 1;
    })
    .catch(showError)
    .finally(() => (isCommenting.value = false));
}

/**
 * Two outcomes behind one gesture. Hiding keeps the row in the thread, greyed, so the moderator can
 * act on it again; removing takes it out. The count only moves when something VISIBLE stopped being
 * visible — removing an already-hidden comment changes nothing a reader could see.
 */
function onCommentDelete(comment: IdeaComment) {
  const WAS_VISIBLE = !comment.hidden;

  DeleteIdeaCommentService(comment.id)
    .then(({ data }) => {
      comments.value = data.deleted
        ? comments.value.filter((item) => item.id !== data.id)
        : comments.value.map((item) => (item.id === data.id ? { ...item, ...data } : item));

      const SHOULD_DECREMENT = WAS_VISIBLE && idea.value && idea.value.commentsCount > 0;

      if (SHOULD_DECREMENT && idea.value) idea.value.commentsCount -= 1;
    })
    .catch(showError);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-lightbulb"
        title="ideas.detail.title"
      >
        <template #actions>
          <Button
            icon="pi pi-arrow-left"
            severity="secondary"
            :label="$t('ideas.actions.backToBoard')"
            @click="onBackClick"
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
      <Skeleton
        v-if="isLoading"
        height="14rem"
      />

      <article
        v-if="!isLoading && idea"
        class="bg-panel shadow-lift flex gap-4 rounded-lg p-5"
      >
        <AIdeaVoteButton
          :count="idea.votesCount"
          :isBusy="isVoting"
          :voted="idea.voted"
          @onToggle="onToggleVote"
        />

        <div class="flex min-w-0 flex-1 flex-col gap-3">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <!-- `h2`, not `h1`: the page already has exactly one, in `ThePageHeader`. -->
            <h2 class="text-heading text-xl font-semibold wrap-anywhere">
              {{ idea.title }}
            </h2>

            <AIdeaStatusTag :status="idea.status" />
          </div>

          <div class="text-muted flex flex-wrap items-center gap-3 text-xs">
            <AIdeaTypeBadge :type="idea.type" />

            <span v-if="idea.area">
              {{ $t(`ideas.area.${idea.area}`) }}
            </span>

            <span>
              {{ idea.author?.name ?? $t('ideas.card.removedAuthor') }}
            </span>

            <span v-if="idea.createdAt">
              {{ formatMoment(idea.createdAt) }}
            </span>
          </div>

          <p class="text-body text-sm wrap-anywhere whitespace-pre-line">
            {{ idea.description }}
          </p>

          <a
            v-if="idea.attachment"
            rel="noopener noreferrer"
            target="_blank"
            :href="idea.attachment.url"
          >
            <img
              class="border-line max-h-96 rounded-md border object-contain"
              :alt="$t('ideas.detail.attachmentAlt')"
              :src="idea.attachment.url"
            />
          </a>

          <div
            v-if="idea.adminNote"
            class="bg-panel-muted border-primary-500 flex flex-col gap-1 rounded-md border p-3"
          >
            <!-- `text-muted`, not a primary shade: the ramp does not flip, and primary-500 on the
                 light panel-muted measured 1.16:1. The brand border already marks the note. -->
            <span class="text-muted text-xs font-medium">
              {{ $t('ideas.detail.maintainerAnswer') }}
            </span>

            <p class="text-body text-sm wrap-anywhere whitespace-pre-line">
              {{ idea.adminNote }}
            </p>
          </div>
        </div>
      </article>

      <MIdeaModerationForm
        v-if="!isLoading && idea && canModerate"
        :idea
        :isSaving="isSavingStatus"
        @onHide="onHideToggle"
        @onMerge="onMergeOpen"
        @onSubmit="onStatusSubmit"
      />

      <!-- The conversation is a panel like everything above it. It sat bare on the canvas, which
           read as the page having ended and the comments having been left outside it. -->
      <section
        v-if="!isLoading && idea"
        class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5"
      >
        <h2 class="text-heading text-sm font-semibold">
          {{ $t('ideas.comments.title') }}
        </h2>

        <p
          v-if="comments.length === 0"
          class="text-muted text-sm"
        >
          {{ $t('ideas.comments.empty') }}
        </p>

        <MIdeaComment
          v-for="comment in comments"
          :key="comment.id"
          :canDelete="canDeleteComment(comment)"
          :comment
          @onDelete="onCommentDelete"
        />

        <div
          v-if="canComment"
          class="flex flex-col gap-2"
        >
          <Textarea
            v-model="commentBody"
            autoResize
            fluid
            :maxlength="IDEA_COMMENT_MAX_LENGTH"
            :placeholder="$t('ideas.comments.placeholder')"
            :rows="3"
          />

          <Button
            class="self-end"
            icon="pi pi-send"
            :disabled="!isCommentValid"
            :label="$t('ideas.comments.send')"
            :loading="isCommenting"
            @click="onCommentSubmit"
          />
        </div>
      </section>
    </div>

    <aside class="contents">
      <MMainDialog
        v-model:visible="isAnnounceVisible"
        isFooterless
        title="news.announce.title"
        @onClose="onAnnounceClose"
      >
        <div class="mx-auto flex w-full max-w-[500px] flex-col gap-4">
          <p class="text-muted text-sm">
            {{ $t('news.announce.hint') }}
          </p>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="announce-title"
            >
              {{ $t('news.form.title') }}
            </label>

            <InputText
              v-model="announceTitle"
              fluid
              id="announce-title"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="announce-body"
            >
              {{ $t('news.form.body') }}
            </label>

            <Textarea
              v-model="announceBody"
              autoResize
              fluid
              id="announce-body"
              :rows="4"
            />
          </div>

          <div class="flex justify-end gap-2">
            <Button
              severity="secondary"
              :label="$t('news.announce.later')"
              @click="onAnnounceClose"
            />

            <Button
              data-testid="announce-publish"
              :disabled="!isAnnounceValid"
              :label="$t('news.announce.publish')"
              :loading="isAnnouncing"
              @click="onAnnounceSubmit"
            />
          </div>
        </div>
      </MMainDialog>

      <MMainDialog
        v-model:visible="isMergeVisible"
        isFooterless
        title="ideas.moderation.mergeTitle"
        @onClose="onMergeClose"
      >
        <div class="mx-auto flex w-full max-w-[500px] flex-col gap-4">
          <p class="text-muted text-sm">
            {{ $t('ideas.moderation.mergeHint') }}
          </p>

          <Select
            v-model="mergeTargetId"
            filter
            fluid
            optionLabel="label"
            optionValue="value"
            :options="mergeOptions"
            :placeholder="$t('ideas.moderation.mergeTarget')"
          />

          <Button
            class="self-end"
            :disabled="!mergeTargetId"
            :label="$t('ideas.moderation.mergeConfirm')"
            @click="onMergeConfirm"
          />
        </div>
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
