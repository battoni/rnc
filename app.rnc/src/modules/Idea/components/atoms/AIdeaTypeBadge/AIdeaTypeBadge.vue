<script setup lang="ts">
import { computed } from 'vue';
import type { IDEA_TYPE } from '../../../enums';
import { IDEA_TYPE_META } from '../../../constants';

/** What kind of thing arrived — quieter than the status, which is the card's real signal. */
const props = defineProps<{
  type: IDEA_TYPE;
}>();

const meta = computed(() => IDEA_TYPE_META[props.type]);
</script>

<template>
  <span class="text-muted inline-flex items-center gap-1.5 text-xs whitespace-nowrap">
    <i :class="[meta.icon, 'text-[0.9em]']" />

    {{ $t(meta.labelKey) }}
  </span>
</template>
