<script setup lang="ts">
import { computed } from 'vue';
import schema from '../../data/schema.json';

const props = defineProps<{ module?: string }>();

type Column = { name: string; type: string; nullable: boolean; default: string | null; fk: string | null; pk: boolean };
type Table = { name: string; module: string; columns: Column[]; indexes: { columns: string[]; unique: boolean }[] };

const tables = computed<Table[]>(() => {
  const all = schema.tables as Record<string, Table>;
  return Object.values(all)
    .filter((table) => !props.module || table.module === props.module)
    .sort((a, b) => a.name.localeCompare(b.name));
});
</script>

<template>
  <div class="schema-book">
    <p
      v-if="!props.module"
      class="source"
    >
      {{ tables.length }} tables · generated from <code>{{ schema.generatedFrom }}</code>
    </p>

    <details
      v-for="table in tables"
      :key="table.name"
      class="table-card"
    >
      <summary>
        <span
          aria-hidden="true"
          class="chevron"
        >▸</span>

        <code class="tname">{{ table.name }}</code>
        <span class="meta">{{ table.columns.length }} cols</span>
      </summary>

      <table>
        <thead>
          <tr>
            <th>Column</th>
            <th>Type</th>
            <th>Null</th>
            <th>Default</th>
            <th>Notes</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="column in table.columns"
            :key="column.name"
          >
            <td><code>{{ column.name }}</code></td>
            <td>{{ column.type }}</td>
            <td>{{ column.nullable ? 'yes' : '—' }}</td>
            <td>{{ column.default ?? '—' }}</td>
            <td>
              <span v-if="column.pk">PK</span>
              <span v-if="column.fk">→ {{ column.fk }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>

<style scoped>
.source {
  font-size: 13px;
  color: var(--vp-c-text-2);
}

.table-card {
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  padding: 8px 12px;
  margin: 8px 0;
}

summary {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  list-style: none;
}

/* Hide the browser's default disclosure triangle in favour of our own chevron. */
summary::-webkit-details-marker {
  display: none;
}

.chevron {
  color: var(--vp-c-brand-1);
  font-size: 12px;
  transition: transform 0.15s ease;
}

.table-card[open] > summary .chevron {
  transform: rotate(90deg);
}

.tname {
  font-size: 14px;
}

.meta {
  font-size: 12px;
  color: var(--vp-c-text-2);
}

table {
  width: 100%;
  display: table;
  margin-top: 8px;
}
</style>
