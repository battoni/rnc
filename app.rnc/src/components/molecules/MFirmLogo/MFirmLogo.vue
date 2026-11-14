<script setup lang="ts">
import { useFirmBrand } from '@Composables';

/*
 * The host firm's mark (rnc-4 R15): `ALogo` with the firm's logo text, for the screens that show the
 * mark on their own — sign-in, terms, not found, the repricing letter. `variant`, `tone` and classes
 * fall through to the mark. Draws nothing until a firm is known (the tab's last answer or the API's),
 * so no name the build carries ever shows. The firm's uploaded logo (`logoUrl`, rnc-4 R27) is the mark
 * when it has one.
 */
const { logoText, logoUrl } = useFirmBrand();
</script>

<template>
  <ALogo
    v-if="logoText"
    :name="logoText"
    :src="logoUrl"
  />
</template>
