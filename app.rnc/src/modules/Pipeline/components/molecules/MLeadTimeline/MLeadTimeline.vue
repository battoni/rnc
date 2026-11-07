<script setup lang="ts">
import type { LeadTimelineEvent } from '../../../interfaces';
import { useLeadFormat } from '../../../composables';
import { LEAD_EVENT_TYPE } from '../../../enums';

/* A lead's timeline (rnc-1 R19), newest first: what happened, when, and who — the visitor or a team member. */
const props = defineProps<{
  events: LeadTimelineEvent[];
}>();

const { formatMoment, sentenceOf } = useLeadFormat();

const TEAM_TYPES: string[] = [LEAD_EVENT_TYPE.NOTE_ADDED, LEAD_EVENT_TYPE.STAGE_CHANGED];
</script>

<template>
  <ol
    class="border-line relative ml-1.5 flex flex-col gap-4 border-l pl-5"
    data-testid="timeline"
  >
    <li
      v-for="event in props.events"
      class="relative flex flex-col gap-0.5"
      data-testid="timeline-item"
      :key="event.id"
      :data-type="event.type"
    >
      <span
        aria-hidden="true"
        :class="[
          'ring-canvas absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full ring-4',
          TEAM_TYPES.includes(event.type) ? 'bg-primary-500' : 'bg-line-strong',
        ]"
      />

      <span class="text-body text-sm break-words">{{ sentenceOf(event) }}</span>

      <span class="text-muted text-xs">
        <time
          class="tabular-nums"
          :datetime="event.at"
        >
          {{ formatMoment(event.at) }}
        </time>

        ·

        <span data-testid="timeline-who">
          {{
            event.user
              ? $t('pipeline.lead.timeline.by', { name: event.user.name })
              : $t('pipeline.lead.timeline.visitor')
          }}
        </span>
      </span>
    </li>
  </ol>
</template>
