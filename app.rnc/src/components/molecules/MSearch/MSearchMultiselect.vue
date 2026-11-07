<script setup lang="ts">
import type { Option } from '@Interfaces';

defineProps<{
  options: Option[];
}>();

const search = defineModel<string>('search');
</script>

<template>
  <InputGroup>
    <InputGroupAddon class="border-none shadow">
      <i class="pi pi-search" />
    </InputGroupAddon>

    <FloatLabel variant="on">
      <MultiSelect
        v-model="search"
        filter
        class="min-w-32 border-none shadow"
        display="chip"
        inputId="search"
        optionLabel="label"
        optionValue="value"
        :options
      />

      <label for="search">{{ $t('common.search') }}</label>
    </FloatLabel>
  </InputGroup>
</template>
