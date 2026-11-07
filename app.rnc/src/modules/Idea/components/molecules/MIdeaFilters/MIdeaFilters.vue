<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { useI18n } from 'vue-i18n';
import type { IdeaFilters } from '../../../interfaces';
import { IDEA_AREA_OPTIONS, IDEA_SORT_OPTIONS, IDEA_STATUS_OPTIONS, IDEA_TYPE_OPTIONS } from '../../../constants';

/** Narrowing the board. Every control is clearable, because the board's first job is the board. */
const emit = defineEmits<{
  onChange: [filters: Partial<IdeaFilters>];
  onReset: [];
}>();

const props = defineProps<{
  filters: IdeaFilters;
}>();

const { t } = useI18n();

/*
 * Typing is debounced; picking is not.
 *
 * The board reloads from the API on every filter change, so an un-debounced text input fired one
 * request per keystroke — fifteen for "total da semana", each one replacing the list. The selects
 * stay immediate because a discrete choice is finished the moment it is made.
 *
 * The local ref is what lets the field stay responsive while the request waits: the input renders
 * from it, and the parent hears about it 300 ms later.
 */
const SEARCH_DEBOUNCE_MS = 300;

const searchDraft = ref(props.filters.search);

const emitSearch = useDebounceFn((value: string) => emit('onChange', { search: value }), SEARCH_DEBOUNCE_MS);

// Keeps the field honest when the parent clears the filters from outside.
watch(() => props.filters.search, onExternalSearchChange);

const hasActiveFilter = computed(
  () =>
    Boolean(props.filters.status) ||
    Boolean(props.filters.type) ||
    Boolean(props.filters.area) ||
    Boolean(props.filters.search) ||
    props.filters.mine
);

const areaOptions = computed(() =>
  IDEA_AREA_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

const sortOptions = computed(() =>
  IDEA_SORT_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

const statusOptions = computed(() =>
  IDEA_STATUS_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

const typeOptions = computed(() =>
  IDEA_TYPE_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

// EVENTS

function onAreaChange(value: IdeaFilters['area']) {
  emit('onChange', { area: value });
}

function onMineChange(value: boolean) {
  emit('onChange', { mine: value });
}

function onResetClick() {
  emit('onReset');
}

function onExternalSearchChange(value: string) {
  if (value !== searchDraft.value) searchDraft.value = value;
}

function onSearchInput(value: string) {
  searchDraft.value = value;

  emitSearch(value);
}

function onSortChange(value: IdeaFilters['sort']) {
  emit('onChange', { sort: value });
}

function onStatusChange(value: IdeaFilters['status']) {
  emit('onChange', { status: value });
}

function onTypeChange(value: IdeaFilters['type']) {
  emit('onChange', { type: value });
}
</script>

<template>
  <!-- Every control GROWS. The four selects were a fixed `basis-40` with no `grow`, so at 360px
       each one dropped onto a line of its own and left 176px of dead canvas beside it — four rows
       of filter to reach a board. A 9rem basis fits two per line on the narrowest phone and they
       share whatever is left over on anything wider. -->
  <div class="flex flex-wrap items-center gap-2">
    <IconField class="grow basis-56">
      <InputIcon class="pi pi-search" />

      <InputText
        fluid
        data-testid="idea-search"
        :aria-label="$t('ideas.filters.search')"
        :modelValue="searchDraft"
        :placeholder="$t('ideas.filters.search')"
        @update:modelValue="onSearchInput($event ?? '')"
      />
    </IconField>

    <Select
      showClear
      class="grow basis-36"
      data-testid="idea-filter-status"
      optionLabel="label"
      optionValue="value"
      :aria-label="$t('ideas.filters.status')"
      :modelValue="props.filters.status"
      :options="statusOptions"
      :placeholder="$t('ideas.filters.status')"
      @update:modelValue="onStatusChange"
    />

    <Select
      showClear
      class="grow basis-36"
      optionLabel="label"
      optionValue="value"
      :aria-label="$t('ideas.filters.type')"
      :modelValue="props.filters.type"
      :options="typeOptions"
      :placeholder="$t('ideas.filters.type')"
      @update:modelValue="onTypeChange"
    />

    <Select
      showClear
      class="grow basis-36"
      optionLabel="label"
      optionValue="value"
      :aria-label="$t('ideas.filters.area')"
      :modelValue="props.filters.area"
      :options="areaOptions"
      :placeholder="$t('ideas.filters.area')"
      @update:modelValue="onAreaChange"
    />

    <Select
      class="grow basis-36"
      optionLabel="label"
      optionValue="value"
      :aria-label="$t('ideas.sort.label')"
      :modelValue="props.filters.sort"
      :options="sortOptions"
      @update:modelValue="onSortChange"
    />

    <div class="flex items-center gap-2">
      <ToggleSwitch
        inputId="idea-filter-mine"
        :modelValue="props.filters.mine"
        @update:modelValue="onMineChange"
      />

      <label
        class="text-muted text-sm"
        for="idea-filter-mine"
      >
        {{ $t('ideas.filters.mine') }}
      </label>
    </div>

    <Button
      v-if="hasActiveFilter"
      text
      icon="pi pi-filter-slash"
      severity="secondary"
      :label="$t('ideas.filters.clear')"
      @click="onResetClick"
    />
  </div>
</template>
