<script setup lang="ts">
import { withBase } from 'vitepress';

declare const __DOCS_VERSION__: string;

// The export page's route. Kept in English like the rest of this portal's paths
// (/guide/, /concepts/); a portal that localises its paths renames the page file
// (export-pdf.md) and this constant together.
const EXPORT_PATH = '/export-pdf';

const docsVersion = typeof __DOCS_VERSION__ !== 'undefined' ? __DOCS_VERSION__ : '0.0.0';
</script>

<template>
  <a
    class="pdf-export-btn"
    :href="withBase(EXPORT_PATH)"
    :title="`Download the user documentation as PDF (v${docsVersion})`"
  >
    PDF
  </a>
</template>

<style scoped>
.pdf-export-btn {
  display: inline-flex;
  align-items: center;
  margin-left: 12px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.4;
  color: var(--vp-c-brand-1);
  text-decoration: none;
  border: 1px solid var(--vp-c-brand-1);
  border-radius: 8px;
  transition:
    color 0.2s,
    background-color 0.2s;
}

.pdf-export-btn:hover {
  color: var(--vp-button-brand-hover-text);
  background: var(--vp-button-brand-bg);
  border-color: var(--vp-button-brand-bg);
}

@media (max-width: 768px) {
  .pdf-export-btn {
    margin: 8px 0 0;
  }
}
</style>
