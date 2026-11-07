<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { NEWS_REACTION } from '../../../enums';
import type { NewsEntry } from '../../../interfaces';
import { API_URL } from '@Constants';
import { withExternalLinks } from '@Helpers';
import useIdeaFormat from '../../../composables/useIdeaFormat';

/** One Novidades entry, read in full: the cover, the headline, the article, where it leads. */
const props = defineProps<{
  entry: NewsEntry;
  /** True while the view is cooling down after a reaction 429 — stops toast spam. */
  reactionsDisabled?: boolean;
}>();

const emit = defineEmits<{ onReact: [type: NEWS_REACTION, settle: () => void] }>();

const toast = useToast();
const { t } = useI18n();

const { formatMoment } = useIdeaFormat();

const hasCoverError = ref(false);

/*
 * The shared address belongs to the API, not to this app, and that is the design rather than an
 * accident. WhatsApp fetches whatever you paste and reads the head without running a line of
 * JavaScript; a single-page app cannot answer that, because every one of its routes returns the
 * same document with the same picture. So the link points where the tags are already true, and
 * that page forwards the person back here.
 */
const shareUrl = computed(() => `${API_URL.replace(/\/api\/?$/, '').replace(/\/$/, '')}/n/${props.entry.id}`);

const cover = computed(() => (hasCoverError.value ? null : props.entry.cover));

watch(() => props.entry.cover?.url, onCoverChange);

// EVENTS

/*
 * The system's own share sheet where there is one — that is where WhatsApp lives, and it is the
 * gesture people already have. A desktop browser usually has none, so the link goes to the
 * clipboard and the toast says so: a button that appears to do nothing is worse than one that
 * tells you what it did.
 *
 * A cancelled share throws `AbortError`. Nothing went wrong, so nothing is said.
 */
function onShareClick() {
  const PAYLOAD = {
    title: props.entry.title,
    text: props.entry.subtitle ?? undefined,
    url: shareUrl.value,
  };

  if (navigator.share) {
    navigator.share(PAYLOAD).catch((error: DOMException) => {
      if (error.name !== 'AbortError') onCopyShareUrl();
    });

    return;
  }

  onCopyShareUrl();
}

function onCopyShareUrl() {
  navigator.clipboard
    .writeText(shareUrl.value)
    .then(() => toast.add({ severity: 'success', summary: t('news.share.copied'), life: 3000 }))
    .catch(() => toast.add({ severity: 'error', summary: t('errors.somethingWentWrong'), life: 5000 }));
}

/* A new cover deserves a new chance: the previous one failing says nothing about this one. */
function onCoverChange() {
  hasCoverError.value = false;
}

/*
 * A cover that 404s used to render its `alt` — the entry's title — as bare text above the article,
 * a second headline in the wrong typeface. The alt is empty because the headline follows two
 * elements later, and the box is dropped rather than kept as a grey rectangle.
 */
function onCoverError() {
  hasCoverError.value = true;
}

function onReact(type: NEWS_REACTION, settle: () => void) {
  emit('onReact', type, settle);
}
</script>

<template>
  <article class="mx-auto flex w-full max-w-[640px] min-w-0 flex-col gap-4 pb-6">
    <img
      v-if="cover"
      alt=""
      class="border-line max-h-72 w-full rounded-md border object-cover"
      data-testid="news-article-cover"
      :src="cover.url"
      @error="onCoverError"
    />

    <div class="flex flex-wrap items-center gap-2">
      <span
        v-if="!props.entry.published"
        class="bg-warn-100 text-warn-700 rounded-xs px-1.5 py-0.5 text-xs font-semibold"
      >
        {{ $t('news.status.draft') }}
      </span>

      <span class="text-muted text-xs">
        {{ formatMoment(props.entry.publishedAt ?? props.entry.createdAt) }}
      </span>
    </div>

    <!-- `h2`, not `h1`: the page already has exactly one, in `ThePageHeader`.

         `wrap-anywhere` for the same reason the body's stylesheet carries `overflow-wrap:
         anywhere` a few lines below — the rule was written for the body and never reached the
         headline above it, which is the one other string a person can paste 300 unbroken
         characters into. -->
    <h2 class="text-heading text-2xl font-semibold wrap-anywhere">
      {{ props.entry.title }}
    </h2>

    <p
      v-if="props.entry.subtitle"
      class="text-muted text-base wrap-anywhere"
    >
      {{ props.entry.subtitle }}
    </p>

    <!-- `v-html` is safe HERE and only here, because the API sanitises the body on WRITE — what is
         stored has already been made safe, so the client is not the thing standing between an
         editor and stored XSS. See the API module's RULES: never move that cleaning to render
         time, and never render a body that came from anywhere but this endpoint.

         `NewsBodySanitizer` strips `target` and `rel` on the way in; `withExternalLinks` puts them
         back as a pair, so a link in the article opens in a new tab instead of taking the reader
         off the SPA — the same helper `ATip` uses. -->
    <div
      class="news-article-body text-body flex min-w-0 flex-col gap-3 text-base leading-relaxed"
      data-testid="news-article-body"
      v-html="withExternalLinks(props.entry.body)"
    />

    <!-- A role token and an underline, not a primary shade: the ramp does not flip, and primary-700
         on the light canvas measured 1.93:1. The underline is what says "link". -->
    <a
      v-if="props.entry.link"
      class="text-heading inline-flex items-center gap-2 text-sm font-medium underline underline-offset-2"
      data-testid="news-article-link"
      rel="noopener noreferrer"
      target="_blank"
      :href="props.entry.link"
    >
      <i class="pi pi-external-link text-xs" />

      {{ props.entry.link }}
    </a>

    <div
      class="border-line flex flex-wrap items-center gap-3 border-t pt-4"
      data-testid="news-article-reactions"
    >
      <!-- `disabled` on a draft, for the same reason as the card: the server answers 404.
           Also while cooling down after a 429. -->
      <MNewsReactions
        :disabled="!props.entry.published || Boolean(props.reactionsDisabled)"
        :myReactions="props.entry.myReactions"
        :reactions="props.entry.reactions"
        @onReact="onReact"
      />

      <!-- Here and not on the card: the card's footer was measured down to 310px, and a fourth
           control there would undo that. Sharing is also a decision made after reading.

           Absent on a draft, because the link it would produce answers 404 — handing someone a
           dead address is worse than offering no button at all. -->
      <Button
        v-if="props.entry.published"
        text
        class="ml-auto"
        data-testid="news-article-share"
        icon="pi pi-share-alt"
        severity="secondary"
        size="small"
        :label="$t('news.share.action')"
        @click="onShareClick"
      />
    </div>

    <p
      v-if="props.entry.idea"
      class="text-muted text-xs"
    >
      {{ $t('news.credit', { author: props.entry.idea.author?.name ?? $t('ideas.card.removedAuthor') }) }}
      <span class="text-body">{{ props.entry.idea.title }}</span>
    </p>
  </article>
</template>

<style scoped>
/* The editor's own markup arrives without classes, so the article's typography is set here rather
   than by utilities the body will never carry. Scoped: this must not leak onto anything else. */

/* Nothing the body contains may push the dialog sideways.
 *
 * A flex child defaults to `min-width: auto`, so one wide thing — a pasted code block, a URL with
 * no spaces in it — grows past the 640px column and takes the whole sheet with it, and the reader
 * gets a horizontal scrollbar under an article. `min-w-0` on the column and on this element lets
 * them shrink; `overflow-wrap` breaks the one word that would otherwise refuse to.
 *
 * A `pre` is the exception that keeps its scroll: code is allowed to be wider than the page, and
 * scrolling it inside its own box is the point of the rule below. What it may not do is carry the
 * page with it, which is exactly what the `min-width` above prevents. */
.news-article-body {
  overflow-wrap: anywhere;
}
/* Sized against the body, not against a fixed idea of "a bit bigger than small". At 1.05rem these
   were 1.2× the old 0.875rem body and read as headings; left alone when the body grew to 1rem they
   would have been 1.05× it — a heading told apart from a paragraph only by its weight. The ratio is
   what does the work, so it is the ratio that was kept. */
.news-article-body :deep(h1),
.news-article-body :deep(h2),
.news-article-body :deep(h3) {
  color: var(--color-heading);
  font-weight: 600;
  font-size: 1.2rem;
}

.news-article-body :deep(ul),
.news-article-body :deep(ol) {
  padding-left: 1.25rem;
  list-style: revert;
}

.news-article-body :deep(a) {
  /* The heading role, not primary-700 (1.93:1 on the light canvas); the underline marks the link. */
  color: var(--color-heading);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.news-article-body :deep(blockquote) {
  border-left: 2px solid var(--color-line-strong);
  padding-left: 0.75rem;
  color: var(--color-muted);
}

.news-article-body :deep(pre) {
  background: var(--color-panel-muted);
  border-radius: 8px;
  padding: 0.75rem;
  overflow-x: auto;
  /* `overflow-wrap` is inherited, and code is the one place it must not reach: breaking a line
     mid-token is worse than a scrollbar. This block scrolls inside its own box, which the
     `min-width: 0` above keeps from becoming the page's problem. */
  overflow-wrap: normal;
}
</style>
