<script setup lang="ts">
import { computed } from 'vue';

/**
 * Support, and the count of it. One control, because supporting and seeing how many others did
 * are the same glance.
 *
 * The count is shown even at zero. A blank where a number belongs reads as "not loaded"; a
 * visible 0 reads as "be the first", which is the invitation the board actually wants to make.
 *
 * The supported state is a filled brand button, and its label therefore takes
 * `text-primary-contrast` — never a raw ink or white, because which one reads is a property of
 * the ACTIVE brand's fill and this component cannot know it. On this bootstrap's lime the ink
 * gives 12.22:1 and white gives 1.51:1; on a darker accent the answer inverts. The token is the
 * theme's to answer.
 *
 * The accessible name is fixed — "Support this idea (3 supporters)" — in both states: the content
 * alone announced a bare number, and `aria-pressed` already says which way the toggle is set, so a
 * name that flipped too would say it twice.
 */
const emit = defineEmits<{
  onToggle: [];
}>();

const props = withDefaults(
  defineProps<{
    count: number;
    voted: boolean;
    isBusy?: boolean;
  }>(),
  { isBusy: false }
);

const classList = computed(() =>
  props.voted
    ? 'bg-primary-500 border-primary-500 text-primary-contrast'
    : 'bg-panel border-line-strong text-body hover:bg-panel-muted'
);

// EVENTS

function onClick() {
  if (props.isBusy) return;

  emit('onToggle');
}
</script>

<template>
  <button
    data-testid="idea-vote"
    type="button"
    :aria-label="$t('ideas.actions.supportCount', { count: props.count }, props.count)"
    :aria-pressed="props.voted"
    :class="[
      'flex w-14 shrink-0 cursor-pointer flex-col items-center gap-0.5 rounded-md border px-2 py-2 transition-colors',
      classList,
    ]"
    :title="props.voted ? $t('ideas.actions.unsupport') : $t('ideas.actions.support')"
    @click="onClick"
  >
    <i :class="[props.isBusy ? 'pi pi-spinner animate-spin' : 'pi pi-chevron-up', 'text-sm']" />

    <span class="text-sm leading-none font-semibold tabular-nums">
      {{ props.count }}
    </span>
  </button>
</template>
