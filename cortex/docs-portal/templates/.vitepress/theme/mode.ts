import { ref, readonly } from 'vue';

export type DocMode = 'user' | 'dev';

const STORAGE_KEY = '{{SLUG}}-doc-mode';

// Module-level singleton so every component reflects the same state.
const mode = ref<DocMode>('user');

function applyClass(value: DocMode): void {
  if (typeof document === 'undefined') return;
  const el = document.documentElement;
  el.classList.toggle('mode-dev', value === 'dev');
  el.classList.toggle('mode-user', value === 'user');
}

/** Sync the ref to whatever the anti-FOUC head script already applied. Client-only. */
export function initDocMode(): void {
  if (typeof localStorage === 'undefined') return;
  const saved = localStorage.getItem(STORAGE_KEY);
  mode.value = saved === 'dev' ? 'dev' : 'user';
  applyClass(mode.value);
}

export function setDocMode(value: DocMode): void {
  mode.value = value;
  applyClass(value);
  try {
    localStorage.setItem(STORAGE_KEY, value);
  } catch {
    // ignore (private mode / storage disabled)
  }
}

export function useDocMode() {
  return { mode: readonly(mode), setDocMode };
}
