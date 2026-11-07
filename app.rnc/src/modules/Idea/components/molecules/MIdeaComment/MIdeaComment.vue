<script setup lang="ts">
import { computed } from 'vue';
import type { IdeaComment } from '../../../interfaces';
import useIdeaFormat from '../../../composables/useIdeaFormat';

/**
 * One comment. The maintainer's reply is lifted out of the chronology by a surface panel with a
 * brand-accent border and a label — a thread where the answer looks like every other message is a
 * thread where nobody finds the answer. Light primary tints (primary-50/100) stay lima in dark
 * scheme and wash out body text; panel-muted + primary-500 work in both schemes.
 */
const emit = defineEmits<{
  onDelete: [comment: IdeaComment];
}>();

const props = withDefaults(
  defineProps<{
    comment: IdeaComment;
    canDelete?: boolean;
  }>(),
  { canDelete: false }
);

const { formatMoment } = useIdeaFormat();

const authorName = computed(() => props.comment.author?.name ?? null);

// EVENTS

function onDeleteClick() {
  emit('onDelete', props.comment);
}
</script>

<template>
  <div
    :class="[
      'flex flex-col gap-2 rounded-md p-3',
      props.comment.isOfficial ? 'bg-panel-muted border-primary-500 border' : 'bg-panel-muted',
      props.comment.hidden ? 'opacity-60' : '',
    ]"
  >
    <div class="flex flex-wrap items-center gap-2">
      <span class="text-heading text-sm font-medium">
        {{ authorName ?? $t('ideas.card.removedAuthor') }}
      </span>

      <span
        v-if="props.comment.isOfficial"
        class="bg-primary-500 text-primary-contrast rounded-xs px-1.5 py-0.5 text-xs font-medium"
      >
        {{ $t('ideas.comments.official') }}
      </span>

      <span
        v-if="props.comment.hidden"
        class="text-contrast-700 inline-flex items-center gap-1 text-xs"
      >
        <i class="pi pi-eye-slash text-[0.9em]" />

        {{ $t('ideas.card.hidden') }}
      </span>

      <span
        v-if="props.comment.createdAt"
        class="text-muted text-xs"
      >
        {{ formatMoment(props.comment.createdAt) }}
      </span>

      <Button
        v-if="props.canDelete"
        text
        class="ml-auto"
        icon="pi pi-trash"
        severity="secondary"
        :title="$t('ideas.comments.delete')"
        @click="onDeleteClick"
      />
    </div>

    <p class="text-body text-sm wrap-anywhere whitespace-pre-line">
      {{ props.comment.body }}
    </p>
  </div>
</template>
