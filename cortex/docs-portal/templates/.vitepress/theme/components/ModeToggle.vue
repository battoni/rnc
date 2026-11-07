<script setup lang="ts">
import { onMounted } from 'vue';
import { initDocMode, setDocMode, useDocMode } from '../mode';

const { mode } = useDocMode();

onMounted(initDocMode);
</script>

<template>
  <div
    aria-label="Documentation depth"
    class="mode-toggle"
    role="group"
  >
    <button
      type="button"
      :aria-pressed="mode === 'user'"
      :class="{ active: mode === 'user' }"
      @click="setDocMode('user')"
    >
      User
    </button>

    <button
      type="button"
      :aria-pressed="mode === 'dev'"
      :class="{ active: mode === 'dev' }"
      @click="setDocMode('dev')"
    >
      Developer
    </button>
  </div>
</template>

<style scoped>
.mode-toggle {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px;
  margin-left: 16px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  background: var(--vp-c-bg-alt);
}

.mode-toggle button {
  padding: 3px 10px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.4;
  color: var(--vp-c-text-2);
  border-radius: 6px;
  transition:
    color 0.2s,
    background-color 0.2s;
}

.mode-toggle button:hover {
  color: var(--vp-c-text-1);
}

.mode-toggle button.active {
  color: var(--vp-button-brand-text);
  background: var(--vp-button-brand-bg);
}

@media (max-width: 768px) {
  .mode-toggle {
    margin: 8px 0;
  }
}
</style>
