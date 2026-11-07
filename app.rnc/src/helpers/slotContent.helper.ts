import { Comment, Fragment, Text } from 'vue';
import type { VNode } from 'vue';

/**
 * Does a slot's rendered output actually draw anything?
 *
 * A slot that exists is not a slot that renders. `v-if="false"` inside it leaves a Comment node
 * behind, a `<template>` wrapper leaves a Fragment, and whitespace leaves empty Text — so
 * `!!slots.actions` answers "did the parent pass one", never "is there a button on THIS row".
 * That gap is what put an empty ⋮ on a row whose every action failed its own `v-if`.
 *
 * Walks Fragments because the content a list passes is almost always wrapped in one: the two
 * branches of a `variant === 'menu'` check are each a `<template>`, and the Comment left by the
 * branch that lost sits inside it.
 */
function hasSlotContent(nodes: VNode[] | undefined): boolean {
  if (!nodes?.length) return false;

  return nodes.some((node) => {
    if (node.type === Comment) return false;

    if (node.type === Text) return String(node.children ?? '').trim() !== '';

    if (node.type === Fragment) return hasSlotContent(node.children as VNode[] | undefined);

    return true;
  });
}

export { hasSlotContent };
