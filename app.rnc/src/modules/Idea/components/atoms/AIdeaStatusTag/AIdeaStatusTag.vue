<script setup lang="ts">
import { computed } from 'vue';
import type { IDEA_STATUS } from '../../../enums';
import { IDEA_STATUS_META } from '../../../constants';

/**
 * The board's answer to "where does this stand", worn by every card and by the detail screen.
 *
 * The colours come from `IDEA_STATUS_META` and nowhere else. In particular `not_now` is the
 * `contrast` ramp, never `danger`: declining an idea is a scoping decision, and error red would
 * turn it into a verdict on whoever wrote it — in a product whose brief says the system does not
 * judge.
 */
const props = withDefaults(
  defineProps<{
    status: IDEA_STATUS;
    isCompact?: boolean;
  }>(),
  { isCompact: false }
);

const meta = computed(() => IDEA_STATUS_META[props.status]);
</script>

<template>
  <span
    data-testid="idea-status"
    :class="[
      'inline-flex items-center gap-1.5 rounded-sm font-medium whitespace-nowrap',
      meta.classList,
      props.isCompact ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm',
    ]"
  >
    <i :class="[meta.icon, 'text-[0.85em]']" />

    {{ $t(meta.labelKey) }}
  </span>
</template>
