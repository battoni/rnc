<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ code: string }>();

const target = ref<HTMLElement>();
const RENDER_ID = `mermaid-${Math.random().toString(36).slice(2)}`;

// `onMounted` only runs in the browser, so mermaid is never imported during SSR — and the target
// <div> is rendered directly, so no <ClientOnly> is needed.
async function render() {
  if (!target.value) return;

  try {
    const mermaid = (await import('mermaid')).default;
    mermaid.initialize({ startOnLoad: false, theme: 'neutral', securityLevel: 'strict' });
    const { svg } = await mermaid.render(RENDER_ID, props.code);
    target.value.innerHTML = svg;
  } catch (error) {
    target.value.textContent = String(error);
  }
}

onMounted(render);
watch(() => props.code, render);
</script>

<template>
  <div
    ref="target"
    class="mermaid-render"
  />
</template>

<style scoped>
.mermaid-render {
  display: flex;
  justify-content: center;
  margin: 16px 0;
  overflow-x: auto;
}

.mermaid-render :deep(svg) {
  height: auto;
  max-width: 100%;
}
</style>
