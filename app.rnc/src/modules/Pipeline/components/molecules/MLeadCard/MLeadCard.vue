<script setup lang="ts">
import { computed } from 'vue';
import type { LeadCard } from '../../../interfaces';
import { getI18nRouteName } from '@Helpers';
import { canMarkLost, nextStage, previousStage, useLeadFormat } from '../../../composables';
import { LEAD_DRAG_TYPE, PIPELINE_ROUTE_NAMES } from '../../../constants';
import { LEAD_CHANNEL, LEAD_STAGE } from '../../../enums';

/*
 * One card of the board (rnc-1 R18): who, where it stopped or what the diagnostic found, the meeting
 * or why it was lost — and, for whoever holds `leads.update`, the ◀ ▶ ✕ moves and the drag. The view
 * owns the move: the card only says where to. A lead that did not come by the form says by which
 * channel (rnc-2 R14: the chat, or Franco's own portfolio).
 */
const emit = defineEmits<{
  onMove: [stage: LEAD_STAGE];
}>();

const props = defineProps<{
  canUpdate: boolean;
  lead: LeadCard;
}>();

const { formatDate, formatMeeting, stageLabel } = useLeadFormat();

const back = computed(() => previousStage(props.lead.stage));
const forward = computed(() => nextStage(props.lead.stage));
const isAnonymous = computed(() => !props.lead.company);
const offersLost = computed(() => canMarkLost(props.lead.stage));

const summaryKey = computed(() => {
  if (props.lead.stage === LEAD_STAGE.VISITANTE) return 'noContact';

  if (props.lead.stage === LEAD_STAGE.LEAD_PARCIAL && props.lead.last_step) return 'stoppedAt';

  return props.lead.opportunities + props.lead.risks > 0 ? 'findings' : 'noDiagnosis';
});

// EVENTS
function onDragStart(event: DragEvent) {
  if (!event.dataTransfer) return;

  event.dataTransfer.setData(LEAD_DRAG_TYPE, String(props.lead.id));
  event.dataTransfer.setData('text/plain', String(props.lead.id));
  event.dataTransfer.effectAllowed = 'move';
}
</script>

<template>
  <article
    class="bg-panel shadow-lift group hover:shadow-lift-lg flex flex-col gap-2.5 rounded-lg p-3.5 transition-shadow"
    data-testid="lead-card"
    :data-lead="props.lead.id"
    :data-stage="props.lead.stage"
    :draggable="props.canUpdate"
    @dragstart="onDragStart"
  >
    <RouterLink
      class="text-heading leading-snug font-semibold break-words underline-offset-4 hover:underline"
      data-testid="lead-name"
      :aria-label="$t('pipeline.card.open', { company: props.lead.company ?? $t('pipeline.anonymous') })"
      :to="{ name: getI18nRouteName(PIPELINE_ROUTE_NAMES.lead), params: { id: props.lead.id } }"
    >
      <span :class="isAnonymous ? 'text-muted font-medium italic' : ''">
        {{ props.lead.company ?? $t('pipeline.anonymous') }}
      </span>
    </RouterLink>

    <div class="text-muted flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
      <span
        v-if="props.lead.sector"
        class="bg-panel-muted text-body rounded-sm px-1.5 py-0.5 font-medium"
      >
        {{ $t(`diagnostic.sectors.${props.lead.sector}`) }}
      </span>

      <MLeadChannelChip
        v-if="props.lead.channel !== LEAD_CHANNEL.FORMULARIO"
        :channel="props.lead.channel"
      />

      <span>{{ $t(`pipeline.sources.${props.lead.source}`) }}</span>

      <span aria-hidden="true">·</span>

      <time
        class="tabular-nums"
        :datetime="props.lead.created_at"
      >
        {{ formatDate(props.lead.created_at) }}
      </time>
    </div>

    <p class="text-body text-sm">
      {{
        $t(`pipeline.${summaryKey}`, {
          opportunities: $t('diagnostic.result.opportunities', props.lead.opportunities),
          risks: $t('diagnostic.result.risks', props.lead.risks),
          step: props.lead.last_step ? $t(`pipeline.steps.${props.lead.last_step}`) : '',
        })
      }}
    </p>

    <p
      v-if="props.lead.meeting && props.lead.stage === LEAD_STAGE.REUNIAO_MARCADA"
      class="text-primary-950 bg-primary-50 flex items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-medium"
      data-testid="lead-meeting"
    >
      <i
        aria-hidden="true"
        class="pi pi-calendar text-xs"
      />

      {{
        $t('pipeline.meetingOn', {
          specialist: props.lead.meeting.specialist,
          when: formatMeeting(props.lead.meeting.starts_at),
        })
      }}
    </p>

    <p
      v-if="props.lead.lost_reason"
      class="text-danger-800 bg-danger-50 rounded-sm px-2 py-1 text-xs break-words"
      data-testid="lead-lost-reason"
    >
      {{ props.lead.lost_reason }}
    </p>

    <div
      v-if="props.canUpdate"
      class="-mb-1 flex items-center justify-end gap-3"
    >
      <Button
        v-if="back"
        rounded
        text
        data-testid="move-prev"
        icon="pi pi-chevron-left"
        severity="secondary"
        size="small"
        :aria-label="$t('pipeline.card.moveBack', { stage: stageLabel(back) })"
        :title="$t('pipeline.card.moveBack', { stage: stageLabel(back) })"
        @click="emit('onMove', back)"
      />

      <Button
        v-if="forward"
        rounded
        text
        data-testid="move-next"
        icon="pi pi-chevron-right"
        severity="secondary"
        size="small"
        :aria-label="$t('pipeline.card.moveForward', { stage: stageLabel(forward) })"
        :title="$t('pipeline.card.moveForward', { stage: stageLabel(forward) })"
        @click="emit('onMove', forward)"
      />

      <Button
        v-if="offersLost"
        rounded
        text
        data-testid="move-lost"
        icon="pi pi-times"
        severity="danger"
        size="small"
        :aria-label="$t('pipeline.card.markLost')"
        :title="$t('pipeline.card.markLost')"
        @click="emit('onMove', LEAD_STAGE.PERDIDO)"
      />
    </div>
  </article>
</template>
