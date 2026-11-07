<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue';
import type { NEWS_REACTION } from '../../../enums';
import type { NewsEntry } from '../../../interfaces';
import useIdeaFormat from '../../../composables/useIdeaFormat';

/**
 * One Novidades entry as it appears in the list: cover, standfirst, applause, and — for whoever
 * writes the changelog — the entry's own actions behind a `⋮`.
 *
 * Extracted from `NewsView` because each card now owns a popover, and a popover needs a template
 * ref per card. A `v-for` in the view cannot give one component instance three refs it can address
 * individually; a component can.
 *
 * THE APPLAUSE IS NOT INSIDE THE CARD'S BUTTON (CRITICAL). The cover and the headline are one big
 * `<button>` that opens the article, and a `<button>` inside a `<button>` is invalid HTML the
 * browser silently unnests — the reaction would end up outside the card and its click would open
 * the article instead of clapping. The footer is a SIBLING of that button, and must stay one.
 */
const emit = defineEmits<{
  onDelete: [target: HTMLElement];
  onEdit: [];
  onOpen: [];
  onPublish: [];
  onReact: [type: NEWS_REACTION, settle: () => void];
  onReaders: [];
}>();

const props = defineProps<{
  canManage?: boolean;
  entry: NewsEntry;
  isPublishing?: boolean;
  /** True while the view is cooling down after a reaction 429 — stops toast spam. */
  reactionsDisabled?: boolean;
}>();

const { formatMoment } = useIdeaFormat();

const overflowPanel = useTemplateRef<{ hide: () => void; toggle: (event: Event, target?: HTMLElement) => void }>(
  'overflowPanel'
);

const hasCoverError = ref(false);

const cover = computed(() => (hasCoverError.value ? null : props.entry.cover));

watch(() => props.entry.cover?.url, onCoverChange);

// EVENTS

/* A new cover deserves a new chance: the previous one failing says nothing about this one. */
function onCoverChange() {
  hasCoverError.value = false;
}

/*
 * A dead URL used to leave the browser rendering the `alt` text — the entry's title, in bare serif,
 * sprawling outside the card and pushing everything under it down. The alt is empty because the
 * headline is right there in text below, and the box is dropped entirely rather than kept as a grey
 * rectangle: a card with no cover is a shape this list already has.
 */
function onCoverError() {
  hasCoverError.value = true;
}

/*
 * The menu STAYS OPEN behind the confirmation, and the anchor is the menu ROW that was clicked.
 *
 * A ConfirmPopup measures the element that asked and points at it. Hiding the menu first detached
 * that element, and the popup rendered at the page's top-left with no `left` or `top` at all and
 * vanished ~250ms later — so an entry could not be deleted from its card at all. Keeping the menu
 * open keeps the row in the DOM, and the popup has something to point at.
 */
function onDeleteClick(event: Event) {
  emit('onDelete', event.currentTarget as HTMLElement);
}

function onEditClick() {
  overflowPanel.value?.hide();

  emit('onEdit');
}

function onOpenClick() {
  emit('onOpen');
}

/*
 * The card is brought into view BEFORE the menu opens, and the menu waits for that scroll to
 * finish.
 *
 * A Popover closes itself when an ancestor scrolls — correctly, because the thing it is anchored
 * to has moved. Opening one on a card low in the viewport made the scroller move to reveal the
 * panel, and the menu shut itself ~350ms later, so none of the three actions could be reached on
 * any card near the fold. Scrolling first removes the reason to scroll later; waiting two frames
 * removes the scroll events that would otherwise land on a menu that is already open.
 *
 * `block: 'nearest'` so a card already in view does not move at all — `'center'` moves every card
 * and reintroduced the bug on all of them.
 */
function onOverflowClick(event: Event) {
  const TRIGGER = event.currentTarget as HTMLElement;

  // Optional-called: jsdom does not implement it, and a unit harness must not lose the menu to a
  // TypeError raised by a scroll that has nothing to do with what it is testing.
  TRIGGER.scrollIntoView?.({ block: 'nearest', behavior: 'instant' });

  /*
   * The TRIGGER is passed explicitly. By the time the frames have passed, `event.currentTarget` is
   * null — it only lives for the duration of the dispatch — and a Popover with no anchor renders
   * at the page's top-left, where nothing the person clicks can reach it.
   */
  requestAnimationFrame(() => requestAnimationFrame(() => overflowPanel.value?.toggle(event, TRIGGER)));
}

function onPublishClick() {
  overflowPanel.value?.hide();

  emit('onPublish');
}

function onReactClick(type: NEWS_REACTION, settle: () => void) {
  emit('onReact', type, settle);
}

function onReadersClick() {
  emit('onReaders');
}
</script>

<template>
  <article
    :aria-label="props.entry.title"
    :class="[
      'bg-panel shadow-lift flex flex-col rounded-lg',
      props.entry.published ? '' : 'border-line-strong border border-dashed',
    ]"
    :data-testid="`news-card-${props.entry.id}`"
  >
    <!-- The whole card opens the article: a cover and a headline that are not clickable are a
         headline somebody has to hunt for a link beside. The image rounds its own top corners —
         `overflow-hidden` on the card would clip the applause confetti off at the footer's edge. -->
    <button
      class="flex w-full cursor-pointer flex-col text-left"
      data-testid="news-open"
      type="button"
      @click="onOpenClick"
    >
      <img
        v-if="cover"
        alt=""
        class="aspect-video w-full rounded-t-lg object-cover"
        data-testid="news-cover"
        :src="cover.url"
        @error="onCoverError"
      />

      <div class="flex flex-col gap-1 p-4">
        <div class="flex flex-wrap items-center gap-2">
          <!-- A draft only ever reaches somebody who may write; the API never sends it to a
               reader. The chip says so out loud anyway, because publishing by accident and not
               publishing by accident are both easy without it. -->
          <span
            v-if="!props.entry.published"
            class="bg-warn-100 text-warn-700 rounded-xs px-1.5 py-0.5 text-xs font-semibold"
            data-testid="news-draft-badge"
          >
            {{ $t('news.status.draft') }}
          </span>

          <span class="text-muted text-xs">
            {{ formatMoment(props.entry.publishedAt ?? props.entry.createdAt) }}
          </span>
        </div>

        <!-- A title is free text and can arrive as a 300-character hash with no space and no
             hyphen. Measured without this, the headline's own box is 439px and its content 2770px:
             the line does not wrap at all, and the overspill paints across the card beside it. The
             grid tracks are fixed, so nothing moves and nothing scrolls — the page looks fine and
             reads as two entries written on top of each other.

             `wrap-anywhere` over `break-words`, though both break the line in this layout:
             `anywhere` also shrinks the element's MIN-CONTENT width, so it keeps holding in a
             track that is allowed to grow. That is the property the article body relies on. -->
        <h3 class="text-heading text-base font-semibold wrap-anywhere">
          {{ props.entry.title }}
        </h3>

        <p
          v-if="props.entry.subtitle"
          class="text-muted text-sm wrap-anywhere"
        >
          {{ props.entry.subtitle }}
        </p>

        <!-- Which parts of the product this is about. After the standfirst, because they answer
             “is this for me?” only once you know what it is — and before the credit, which is
             about where the entry came from rather than what it touches. -->
        <div
          v-if="props.entry.areas?.length"
          class="flex flex-wrap gap-1.5"
          data-testid="news-card-areas"
        >
          <Tag
            v-for="area in props.entry.areas"
            :key="area"
            severity="secondary"
            :value="$t(`ideas.area.${area}`)"
          />
        </div>

        <p
          v-if="props.entry.idea"
          class="text-muted text-xs"
        >
          {{ $t('news.credit', { author: props.entry.idea.author?.name ?? $t('ideas.card.removedAuthor') }) }}
          <span class="text-body">{{ props.entry.idea.title }}</span>
        </p>
      </div>
    </button>

    <!-- Applause on the left, the writer's tools on the right. Somebody scrolling the changelog can
         clap without opening the entry — the gesture costs nothing and asking for a dialog first is
         how it stops happening. -->
    <div class="border-line flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2">
      <!-- `disabled` on a draft: the server refuses applause for an unpublished entry (404), so a
           writer clapping their own draft only got an error toast. Also while the view is cooling
           down after a 429 — further clicks would only spam the same toast. -->
      <MNewsReactions
        :disabled="!props.entry.published || Boolean(props.reactionsDisabled)"
        :myReactions="props.entry.myReactions"
        :reactions="props.entry.reactions"
        @onReact="onReactClick"
      />

      <!-- Own row (`basis-full` / `w-full`): applause stays on the first footer line; eye and ⋮
           share the second with `justify-between`. `gap-3` alone left them clustered at the start
           of an empty line once wrap kicked in — and sharing a row with applause on desktop put
           the tools beside the claps instead of under them. Both controls grow their hit area by
           6px a side on a touch screen, so 4px between them left the areas overlapping by 8px and
           the LATER sibling — the menu — winning: a thumb aimed at the audience opened actions.
           12px is exactly 6 + 6. -->
      <div
        v-if="props.canManage"
        class="flex w-full basis-full items-center justify-between gap-3"
      >
        <!-- The audience, and a way into who it was. A number nobody can open is a number nobody
             trusts — and it is information, so it stays on the card rather than going in the menu.
             `touch-target` because a hand-written button gets none of the preset's thumb sizing. -->
        <!-- The NUMBER beside the eye, not the sentence. Spelled out, “6 leituras” cost 79px of a
             footer that has 305 to give, and it repeated what the icon already says — the width it
             bought back is a whole column on a wide screen. The sentence survives where it is
             still needed: the title, for a pointer, and the accessible name, for a screen reader,
             which reads neither icons nor context. -->
        <button
          class="text-muted hover:text-body motion-instant touch-target flex shrink-0 cursor-pointer items-center gap-1.5 rounded-sm px-1.5 py-1 text-xs transition-colors"
          type="button"
          :aria-label="$t('news.readers.count', { count: props.entry.readCount ?? 0 })"
          :data-testid="`news-readers-${props.entry.id}`"
          :title="$t('news.readers.count', { count: props.entry.readCount ?? 0 })"
          @click="onReadersClick"
        >
          <i class="pi pi-eye text-xs" />

          {{ props.entry.readCount ?? 0 }}
        </button>

        <!-- Three controls on every card, on every row, competed with the writing they sat under.
             One `⋮` per card instead — the same pattern `ThePageHeader` uses for a screen's
             secondary actions. -->
        <Button
          text
          icon="pi pi-ellipsis-v"
          severity="secondary"
          :aria-label="$t('common.moreActions')"
          :data-testid="`news-actions-${props.entry.id}`"
          @click="onOverflowClick"
        />

        <Popover ref="overflowPanel">
          <!-- `gap-2` between rows and a rule above the destructive one. A menu is a vertical
               stack of thumb targets, and the pair that must never be confused is Editar and
               Apagar: the separator buys 12px plus a line between them, so reaching for the pencil
               cannot land on the bin. -->
          <div class="flex min-w-[13rem] flex-col items-stretch gap-2">
            <Button
              text
              class="justify-start"
              :data-testid="`news-publish-${props.entry.id}`"
              :icon="props.entry.published ? 'pi pi-eye-slash' : 'pi pi-send'"
              :label="props.entry.published ? $t('news.actions.unpublish') : $t('news.actions.publish')"
              :loading="props.isPublishing"
              :severity="props.entry.published ? 'secondary' : 'primary'"
              @click="onPublishClick"
            />

            <Button
              text
              class="justify-start"
              icon="pi pi-pencil"
              severity="secondary"
              :data-testid="`news-edit-${props.entry.id}`"
              :label="$t('news.actions.edit')"
              @click="onEditClick"
            />

            <div class="border-line mt-1 border-t pt-3">
              <Button
                text
                class="w-full justify-start"
                icon="pi pi-trash"
                severity="danger"
                :data-testid="`news-delete-${props.entry.id}`"
                :label="$t('news.actions.delete')"
                @click="onDeleteClick"
              />
            </div>
          </div>
        </Popover>
      </div>
    </div>
  </article>
</template>
