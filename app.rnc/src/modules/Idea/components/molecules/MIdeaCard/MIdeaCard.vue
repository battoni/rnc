<script setup lang="ts">
import { computed } from 'vue';
import type { Idea } from '../../../interfaces';

/**
 * One idea on the board: support on the left, the idea in the middle, its standing on the right.
 *
 * The support control is a sibling of the link rather than inside it — a button nested in a
 * navigating anchor is a click the person cannot aim, and supporting must never cost them the
 * screen they were reading.
 */
const emit = defineEmits<{
  onEdit: [idea: Idea];
  onOpen: [id: number];
  onToggleVote: [idea: Idea];
}>();

const props = withDefaults(
  defineProps<{
    idea: Idea;
    /** Quietly true when this is an idea the viewer supports whose status moved since they looked. */
    hasMoved?: boolean;
    isVoting?: boolean;
  }>(),
  { hasMoved: false, isVoting: false }
);

const authorName = computed(() => props.idea.author?.name ?? null);
const hasNote = computed(() => Boolean(props.idea.adminNote));

// EVENTS

function onEditClick() {
  emit('onEdit', props.idea);
}

function onOpenClick() {
  emit('onOpen', props.idea.id);
}

function onToggleVote() {
  emit('onToggleVote', props.idea);
}
</script>

<template>
  <article
    :aria-label="props.idea.title"
    :class="['bg-panel shadow-lift flex gap-4 rounded-lg p-4', props.idea.hidden ? 'opacity-60' : '']"
    :data-testid="`idea-card-${props.idea.id}`"
  >
    <AIdeaVoteButton
      :count="props.idea.votesCount"
      :isBusy="props.isVoting"
      :voted="props.idea.voted"
      @onToggle="onToggleVote"
    />

    <div class="flex min-w-0 flex-1 flex-col gap-2">
      <!-- `gap-3` and `touch-target`: the title is a one-line button, and on a touch screen its hit
           area grows 6px; 12px keeps it from overlapping the pencil beside it (celer-06). -->
      <div class="flex flex-wrap items-start justify-between gap-3">
        <button
          class="text-heading touch-target cursor-pointer text-left text-base font-semibold wrap-anywhere hover:underline"
          data-testid="idea-open"
          type="button"
          @click="onOpenClick"
        >
          {{ props.idea.title }}
        </button>

        <div class="flex shrink-0 items-center gap-1">
          <!-- A dot, and nothing else. The brief rules out urgency, alarm and guilt, so the fact
               that something moved is offered rather than announced: no red, no count, no badge in
               the navigation. Someone who never notices it loses nothing. -->
          <span
            v-if="props.hasMoved"
            class="bg-primary-500 inline-block size-2 shrink-0 rounded-xs"
            data-testid="idea-moved"
            role="img"
            :aria-label="$t('ideas.card.moved')"
            :title="$t('ideas.card.moved')"
          />

          <!-- `canEdit` comes from the API, which is also what enforces it. The client never
               recomputes the rule: two copies is how a button and an endpoint start disagreeing. -->
          <Button
            v-if="props.idea.canEdit"
            text
            data-testid="idea-edit"
            icon="pi pi-pencil"
            severity="secondary"
            :title="$t('ideas.actions.edit')"
            @click="onEditClick"
          />

          <!-- A fixed column for the status, so the pencils line up down the list.
               The tags are right-aligned but their labels are not the same length — "Planejada"
               against "Em construção" — so each card put its edit button somewhere different, and
               a column of controls that does not line up reads as a list that is broken. The tag
               keeps its own pill width inside the column; only the slot is fixed. Phones keep the
               tight rhythm, where there is one card per row and nothing to line up with. -->
          <div class="flex shrink-0 justify-end sm:min-w-[8.5rem]">
            <AIdeaStatusTag
              isCompact
              :status="props.idea.status"
            />
          </div>
        </div>
      </div>

      <p class="text-body line-clamp-2 text-sm wrap-anywhere">
        {{ props.idea.description }}
      </p>

      <p
        v-if="hasNote"
        class="border-primary-300 text-muted border-l-2 pl-3 text-sm wrap-anywhere italic"
        data-testid="idea-admin-note"
      >
        {{ props.idea.adminNote }}
      </p>

      <div class="text-muted flex flex-wrap items-center gap-3 text-xs">
        <AIdeaTypeBadge :type="props.idea.type" />

        <span v-if="props.idea.area">
          {{ $t(`ideas.area.${props.idea.area}`) }}
        </span>

        <span>
          {{ authorName ?? $t('ideas.card.removedAuthor') }}
        </span>

        <span
          v-if="props.idea.commentsCount > 0"
          class="inline-flex items-center gap-1"
        >
          <i class="pi pi-comment text-[0.9em]" />

          {{ props.idea.commentsCount }}
        </span>

        <span
          v-if="props.idea.attachment"
          class="inline-flex items-center gap-1"
        >
          <i class="pi pi-image text-[0.9em]" />

          {{ $t('ideas.card.hasImage') }}
        </span>

        <span
          v-if="props.idea.hidden"
          class="text-contrast-700 inline-flex items-center gap-1"
        >
          <i class="pi pi-eye-slash text-[0.9em]" />

          {{ $t('ideas.card.hidden') }}
        </span>
      </div>
    </div>
  </article>
</template>
