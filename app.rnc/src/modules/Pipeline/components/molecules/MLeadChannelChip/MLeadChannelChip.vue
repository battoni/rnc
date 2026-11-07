<script setup lang="ts">
import type { LEAD_CHANNEL } from '../../../enums';
import { LEAD_CHANNEL_ICONS } from '../../../constants';

/*
 * The channel a lead came in by (rnc-2 R14): formulário, chat or carteira, with its icon. The board
 * card shows it only when it is not the form; the lead detail always does.
 */
const props = defineProps<{
  channel: LEAD_CHANNEL;
}>();
</script>

<template>
  <span
    class="bg-panel-muted text-body inline-flex w-fit items-center gap-1 rounded-sm px-1.5 py-0.5 text-xs font-medium"
    data-testid="lead-channel"
    :data-channel="props.channel"
    :title="$t('pipeline.channelLabel', { channel: $t(`pipeline.channels.${props.channel}`) })"
  >
    <i
      aria-hidden="true"
      :class="[LEAD_CHANNEL_ICONS[props.channel], 'text-xs']"
    />

    {{ $t(`pipeline.channels.${props.channel}`) }}
  </span>
</template>
