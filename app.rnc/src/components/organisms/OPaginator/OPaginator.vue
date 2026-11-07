<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const emit = defineEmits<{
  onPage: [page: number];
}>();

const props = defineProps<{
  page: number;
  perPage: number;
  total: number;
}>();

const { t } = useI18n();

// PrimeVue fills `{currentPage}` and `{totalPages}` itself, so the catalogue hands them back untouched.
const PAGE_REPORT_PLACEHOLDERS = { currentPage: '{currentPage}', totalPages: '{totalPages}' };

/*
 * 44px buttons 12px apart under a finger; the mouse keeps PrimeVue's 40px density. A bound `pt`
 * and not `pt:first="…"` attributes: the page links are child components, and Paginator hands
 * them its `pt` prop only — an attribute shorthand never reaches them.
 */
const TOUCH_BUTTON_CLASS = 'pointer-coarse:min-h-11 pointer-coarse:min-w-11';
const PAGINATOR_PT = {
  content: { class: 'pointer-coarse:gap-3' },
  first: { class: TOUCH_BUTTON_CLASS },
  last: { class: TOUCH_BUTTON_CLASS },
  next: { class: TOUCH_BUTTON_CLASS },
  prev: { class: TOUCH_BUTTON_CLASS },
};

const first = computed(() => (props.page - 1) * props.perPage);
const hasMultiplePages = computed(() => props.total > props.perPage);
const pageReportTemplate = computed(() => t('common.paginator.report', PAGE_REPORT_PLACEHOLDERS));

// EVENTS
function onPageChange(event: { page: number }) {
  emit('onPage', event.page + 1);
}
</script>

<template>
  <Paginator
    v-if="hasMultiplePages"
    class="mt-4 justify-center"
    template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
    :currentPageReportTemplate="pageReportTemplate"
    :first
    :pt="PAGINATOR_PT"
    :rows="perPage"
    :totalRecords="total"
    @page="onPageChange"
  />
</template>
