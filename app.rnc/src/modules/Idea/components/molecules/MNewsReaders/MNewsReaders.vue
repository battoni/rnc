<script setup lang="ts">
import type { NewsReader } from '../../../interfaces';
import useIdeaFormat from '../../../composables/useIdeaFormat';

/**
 * Who read an entry, newest first.
 *
 * Deliberately a name and a moment and nothing else. This answers the writer's question — "did
 * anybody read what I published" — and widening it into profiles would turn a feedback loop into a
 * directory of everyone's reading habits.
 */
defineProps<{
  isLoading?: boolean;
  readers: NewsReader[];
}>();

const { formatMoment } = useIdeaFormat();
</script>

<template>
  <div class="flex flex-col gap-3">
    <template v-if="isLoading">
      <Skeleton height="2.5rem" />

      <Skeleton height="2.5rem" />
    </template>

    <p
      v-else-if="!readers.length"
      class="text-muted py-6 text-center text-sm"
      data-testid="news-readers-empty"
    >
      {{ $t('news.readers.empty') }}
    </p>

    <ul
      v-else
      class="divide-line flex flex-col divide-y"
      data-testid="news-readers-list"
    >
      <li
        v-for="reader in readers"
        class="flex items-center justify-between gap-4 py-2.5"
        :key="reader.id"
      >
        <span class="text-body truncate text-sm">
          {{ reader.user?.name ?? $t('ideas.card.removedAuthor') }}
        </span>

        <span class="text-muted shrink-0 text-xs">
          {{ formatMoment(reader.readAt) }}
        </span>
      </li>
    </ul>
  </div>
</template>
