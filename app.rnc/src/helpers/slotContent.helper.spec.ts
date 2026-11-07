import { Comment, Fragment, h, Text } from 'vue';
import { describe, expect, it } from 'vitest';
import { hasSlotContent } from './slotContent.helper';

describe('hasSlotContent', () => {
  it('says no to a slot that rendered nothing at all', () => {
    expect(hasSlotContent(undefined)).toBe(false);
    expect(hasSlotContent([])).toBe(false);
  });

  it('says no to the Comment a false v-if leaves behind', () => {
    expect(hasSlotContent([h(Comment, 'v-if')])).toBe(false);
  });

  it('says no to whitespace, and yes to real text', () => {
    expect(hasSlotContent([h(Text, null, '  ')])).toBe(false);
    expect(hasSlotContent([h(Text, null, 'History')])).toBe(true);
  });

  it('looks inside a Fragment, which is what a <template> wrapper leaves', () => {
    // The shape a list's `#actions` slot really produces: two `<template>` branches, the losing
    // one reduced to a Comment.
    expect(hasSlotContent([h(Fragment, null, [h(Comment, 'v-if'), h(Comment, 'v-if')])])).toBe(false);
    expect(hasSlotContent([h(Fragment, null, [h(Comment, 'v-if'), h('button', 'Archive')])])).toBe(true);
  });

  it('says yes to an element', () => {
    expect(hasSlotContent([h('button', 'Delete')])).toBe(true);
  });
});
