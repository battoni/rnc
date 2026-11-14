<script setup lang="ts">
import { useFirmBrand } from '@Composables';

/*
 * The back office's brand block (rnc-4 R10): the host firm's tile and logo text, and the product's
 * name under them (`platform.name`, R9) — the same block in the rail and in the drawer. Both come from
 * the firm's answer (`useFirmBrand`: the tab's last one until the API replies); until a firm is known
 * the block stays empty rather than writing a name the build carries.
 */
const { logoText, logoUrl, platformName } = useFirmBrand();
</script>

<template>
  <div
    v-if="logoText"
    class="flex w-full min-w-0 flex-col items-center gap-2 text-center"
  >
    <span class="w-full max-w-[50px]">
      <ALogo
        variant="min"
        :name="logoText"
        :src="logoUrl"
      />
    </span>

    <span
      class="text-heading w-full truncate text-sm font-semibold"
      data-testid="navbar-firm-name"
    >
      {{ logoText }}
    </span>

    <span
      v-if="platformName"
      class="text-muted w-full truncate text-xs"
      data-testid="navbar-platform-name"
    >
      {{ platformName }}
    </span>
  </div>
</template>
