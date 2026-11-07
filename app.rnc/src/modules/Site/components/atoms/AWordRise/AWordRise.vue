<script setup lang="ts">
import { computed } from 'vue';

/*
 * A line of text whose words rise one after the other from behind a mask on mount
 * (`.motion-word`, `styles/components/motion.css`). The hero's headline.
 *
 * The spaces between the words are rendered from script, as one text node each: written in the
 * template, the whitespace around `{{ ' ' }}` condenses into a second space and the heading's
 * `textContent` no longer reads as the sentence it is.
 */
const props = defineProps<{
  /** The entrance index the first word takes; the next line continues from `startIndex + words`. */
  startIndex: number;
  text: string;
}>();

const words = computed(() => props.text.split(' '));
</script>

<template>
  <span>
    <template
      v-for="(word, index) in words"
      :key="`${index}-${word}`"
    >
      <span
        class="motion-word"
        :style="{ '--reveal-index': props.startIndex + index }"
      >
        <span>{{ word }}</span>
      </span>
      <template v-if="index < words.length - 1">{{ ' ' }}</template>
    </template>
  </span>
</template>
