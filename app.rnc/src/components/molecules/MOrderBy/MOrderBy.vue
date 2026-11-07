<script setup lang="ts">
import type { Option } from '@Interfaces';

defineProps<{
  options: Option[];
}>();

const orderBy = defineModel<string>('orderBy');
</script>

<template>
  <InputGroup class="w-fit">
    <InputGroupAddon class="border-none shadow">
      <i class="pi pi-sort-alpha-up-alt" />
    </InputGroupAddon>

    <FloatLabel variant="on">
      <Select
        v-model="orderBy"
        showClear
        class="min-w-36 border-none shadow"
        inputId="sort-by"
        optionLabel="label"
        optionValue="value"
        :options
      />

      <label for="sort-by">{{ $t('common.orderBy') }}</label>
    </FloatLabel>
  </InputGroup>
</template>
