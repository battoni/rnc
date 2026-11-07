<script setup lang="ts">
/*
 * Project-owned overlays — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * A module whose dialog is opened from a store, from anywhere, has to be mounted above every
 * authenticated screen — and that is TheLayout, which the sync owns. Mount such overlays here
 * instead of editing TheLayout, which would conflict on every sync. rnc ships it empty.
 *
 * Example:
 *   <template>
 *     <OCatalogQuickAddDialog />
 *   </template>
 */
</script>

<template>
  <div
    data-local-overlays
    class="contents"
  />
</template>
