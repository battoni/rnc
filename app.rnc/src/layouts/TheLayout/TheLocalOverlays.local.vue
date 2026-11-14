<script setup lang="ts">
/*
 * Project-owned overlays — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * A module whose dialog is opened from a store, from anywhere, has to be mounted above every
 * authenticated screen — and that is TheLayout, which the sync owns. Mount such overlays here
 * instead of editing TheLayout, which would conflict on every sync. rnc mounts the view-as banner
 * (`OViewAsBanner`, rnc-4 R26): a child of `<main>`'s column through this `contents` box, pulled to the top
 * with `order-first`.
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
  >
    <!-- rnc-4 R26: the view-as banner, on every back-office screen while the operator is inside a firm. -->
    <OViewAsBanner />
  </div>
</template>
