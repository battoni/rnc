import { ref } from 'vue';

const activeClose = ref<(() => void) | null>(null);

function openTip(close: () => void) {
  if (activeClose.value && activeClose.value !== close) activeClose.value();

  activeClose.value = close;
}

function closeTip(close: () => void) {
  if (activeClose.value !== close) return;

  activeClose.value = null;
}

function resetSingleTipState() {
  const pendingClose = activeClose.value;

  activeClose.value = null;

  if (pendingClose) pendingClose();
}

function useSingleTip() {
  return { closeTip, openTip };
}

export { closeTip, openTip, resetSingleTipState, useSingleTip };
