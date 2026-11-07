<script setup lang="ts">
/*
 * A split of the funnel as a table (rnc-1 R24): the first column names the row (a source, a sector
 * page), the others are numbers already formatted by the view. The frame scrolls sideways on a
 * narrow screen so the page never does (`w-0 min-w-full`: state.md Pitfalls).
 */
interface Row {
  cells: string[];
  key: string;
  label: string;
}

interface Props {
  caption: string;
  headers: string[];
  rows: Row[];
  testId: string;
}

defineProps<Props>();
</script>

<template>
  <div class="border-line w-0 min-w-full overflow-x-auto rounded-md border">
    <table class="w-full min-w-[34rem] border-collapse text-sm">
      <caption class="sr-only">
        {{
          caption
        }}
      </caption>

      <thead class="bg-panel-muted">
        <tr>
          <th
            v-for="(header, index) in headers"
            :key="header"
            scope="col"
            :class="[
              'text-muted px-3 py-2.5 text-xs font-semibold whitespace-nowrap',
              index === 0 ? 'text-left' : 'text-right',
            ]"
          >
            {{ header }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="row in rows"
          class="border-line border-t"
          :key="row.key"
          :data-key="row.key"
          :data-testid="testId"
        >
          <th
            class="text-heading px-3 py-2.5 text-left font-medium whitespace-nowrap"
            scope="row"
          >
            {{ row.label }}
          </th>

          <td
            v-for="(cell, index) in row.cells"
            class="text-body px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
            :key="index"
          >
            {{ cell }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
