<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Option } from '@Interfaces';

const { t } = useI18n();

const ORDER_BY_KEYS = [
  { key: 'nameAsc', value: 'name_asc' },
  { key: 'nameDesc', value: 'name_desc' },
  { key: 'dateDesc', value: 'date_desc' },
  { key: 'dateAsc', value: 'date_asc' },
];

const longDialogVisible = ref(false);
const orderBy = ref('name_asc');
const search = ref('');
const shortDialogVisible = ref(false);

const isAnyDialogOpen = computed(() => longDialogVisible.value || shortDialogVisible.value);

const orderByOptions = computed<Option[]>(() =>
  ORDER_BY_KEYS.map(({ key, value }) => ({ label: t(`showcase.showcase.orderBy.${key}`), value }))
);

// EVENTS
function onLongFormSuccess() {
  longDialogVisible.value = false;
}

function onShortFormSuccess() {
  shortDialogVisible.value = false;
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="showcase.showcase.title">
        <template
          v-if="!isAnyDialogOpen"
          #actions
        >
          <div class="flex items-center gap-3">
            <Button
              class="celer-button-soft-primary shadow-none"
              icon="pi pi-download"
              :label="$t('showcase.showcase.export')"
              @click="shortDialogVisible = true"
            />

            <Button
              class="celer-button-primary shadow-none"
              icon="pi pi-plus"
              :label="$t('showcase.showcase.addItem')"
              @click="longDialogVisible = true"
            />
          </div>
        </template>
      </ThePageHeader>
    </template>

    <TheFilters>
      <InputGroup class="sm:w-fit">
        <InputGroupAddon class="border-none shadow">
          <i class="pi pi-search" />
        </InputGroupAddon>

        <InputText
          v-model="search"
          class="border-none shadow"
          :placeholder="$t('showcase.showcase.searchPlaceholder')"
        />
      </InputGroup>

      <MOrderBy
        v-model:orderBy="orderBy"
        :options="orderByOptions"
      />
    </TheFilters>

    <aside class="contents">
      <MMainDialog
        v-model:visible="shortDialogVisible"
        isFooterless
        title="showcase.showcase.export"
      >
        <MShowcaseShortForm
          v-if="shortDialogVisible"
          @onClose="shortDialogVisible = false"
          @onSuccess="onShortFormSuccess"
        />
      </MMainDialog>

      <MMainDialog
        v-model:visible="longDialogVisible"
        isFooterless
        title="showcase.showcase.addItem"
      >
        <MShowcaseLongForm
          v-if="longDialogVisible"
          @onClose="longDialogVisible = false"
          @onSuccess="onLongFormSuccess"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
