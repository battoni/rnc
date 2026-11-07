<script setup lang="ts">
import { computed, ref } from 'vue';
import type { NfeImport } from '../../../interfaces';
import { useFiscalFormat } from '../../../composables';
import { REFUSALS_PREVIEW } from '../../../constants';
import { NFE_IMPORT_SOURCE } from '../../../enums';

/*
 * One import batch (rnc-2 R17): how it came in (the screen's upload, by whom, or the folder
 * command), when, the files read, the documents stored and every refusal by file with its reason
 * in words — never the bare code. A batch just uploaded opens its refusals; a listed one keeps them
 * folded until asked. Past `REFUSALS_PREVIEW` lines the rest wait behind "Ver todas".
 */
interface Props {
  batch: NfeImport;
  isExpanded?: boolean;
}

const props = withDefaults(defineProps<Props>(), { isExpanded: false });

const { formatCount, formatMoment } = useFiscalFormat();

const isOpen = ref(props.isExpanded);
const showsAll = ref(false);

const hasRefusals = computed(() => props.batch.refused.length > 0);
const hiddenCount = computed(() => Math.max(0, props.batch.refused.length - REFUSALS_PREVIEW));

const figures = computed(() => [
  { key: 'files', testId: 'nfe-batch-files', tone: 'text-heading', value: props.batch.files },
  { key: 'stored', testId: 'nfe-batch-stored', tone: 'text-heading', value: props.batch.stored },
  {
    key: 'refused',
    testId: 'nfe-batch-refused',
    tone: 'text-heading',
    value: props.batch.refused.length,
  },
]);

const shownRefusals = computed(() =>
  showsAll.value ? props.batch.refused : props.batch.refused.slice(0, REFUSALS_PREVIEW)
);
</script>

<template>
  <article
    class="border-line flex min-w-0 flex-col gap-3 rounded-md border p-4"
    data-testid="nfe-batch"
    :data-id="batch.id"
    :data-source="batch.source"
  >
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
      <span class="text-heading flex items-center gap-2 text-sm font-semibold">
        <i
          aria-hidden="true"
          :class="[batch.source === NFE_IMPORT_SOURCE.FOLDER ? 'pi pi-folder-open' : 'pi pi-upload', 'text-subtle']"
        />

        {{ $t(`fiscal.imports.batch.${batch.source}`) }}
      </span>

      <span class="text-muted text-xs tabular-nums">
        {{ formatMoment(batch.at) }}
        <template v-if="batch.by_name">· {{ $t('fiscal.imports.batch.by', { name: batch.by_name }) }}</template>
      </span>
    </div>

    <dl class="grid grid-cols-3 gap-3">
      <div
        v-for="figure in figures"
        class="bg-panel-muted flex min-w-0 flex-col gap-0.5 rounded-md px-3 py-2"
        :key="figure.key"
      >
        <dt class="text-muted text-xs font-medium">{{ $t(`fiscal.imports.batch.${figure.key}`) }}</dt>

        <dd
          :class="['text-lg font-semibold tabular-nums', figure.tone]"
          :data-testid="figure.testId"
        >
          {{ formatCount(figure.value) }}
        </dd>
      </div>
    </dl>

    <p
      v-if="!hasRefusals"
      class="text-muted text-xs"
    >
      {{ $t('fiscal.imports.batch.noRefusals') }}
    </p>

    <template v-else>
      <Button
        v-if="!isExpanded"
        text
        class="w-fit"
        data-testid="nfe-batch-toggle"
        severity="secondary"
        size="small"
        :aria-expanded="isOpen ? 'true' : 'false'"
        :icon="isOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
        :label="
          isOpen
            ? $t('fiscal.imports.batch.hideRefusals')
            : $t('fiscal.imports.batch.showRefusals', { count: formatCount(batch.refused.length) })
        "
        @click="isOpen = !isOpen"
      />

      <ul
        v-if="isOpen"
        class="flex flex-col gap-1.5"
      >
        <li
          v-for="(refusal, index) in shownRefusals"
          class="motion-enter flex min-w-0 flex-col gap-0.5 sm:flex-row sm:items-baseline sm:gap-2"
          data-testid="nfe-refusal"
          :key="`${refusal.file}-${index}`"
          :data-code="refusal.code"
        >
          <code class="text-body min-w-0 text-xs break-all">{{ refusal.file }}</code>

          <span class="bg-danger-50 text-danger-800 w-fit shrink-0 rounded-sm px-1.5 py-0.5 text-xs font-medium">
            {{ $t(`fiscal.imports.refusals.${refusal.code}`) }}
          </span>
        </li>
      </ul>

      <Button
        v-if="isOpen && hiddenCount > 0 && !showsAll"
        text
        class="w-fit"
        severity="secondary"
        size="small"
        :label="$t('fiscal.imports.batch.all', { count: formatCount(batch.refused.length) })"
        @click="showsAll = true"
      />
    </template>
  </article>
</template>
