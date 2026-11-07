<script setup lang="ts">
import { computed } from 'vue';
import schema from '../../data/schema.json';
import MermaidRender from './MermaidRender.vue';

/*
 * The ER diagram of one module, generated from the extracted schema. The database is never redrawn
 * by hand: this is a function of the migrations, so it cannot fall behind them.
 */
const props = defineProps<{ module: string }>();

type Column = { name: string; type: string; pk: boolean; fk: string | null };
type Table = { name: string; module: string; columns: Column[] };

const tables = computed(() =>
  Object.values(schema.tables as Record<string, Table>).filter((table) => table.module === props.module)
);

/**
 * A name as mermaid may read it. Table and column names come from the migrations, but the source is
 * a diagram language: a quote, a brace or a newline in a name would write entities of its own.
 */
function identifier(name: string): string {
  return name.replace(/[^a-zA-Z0-9_]/g, '_');
}

function simpleType(type: string): string {
  return (type.replace(/\(.*\)/, '').trim() || 'column').replace(/[^a-zA-Z0-9_]/g, '');
}

const code = computed(() => {
  const names = new Set(tables.value.map((table) => table.name));
  const relations: string[] = [];
  const blocks: string[] = [];

  for (const table of tables.value) {
    const lines = table.columns.map((column) => {
      const marker = column.pk ? ' PK' : column.fk ? ' FK' : '';
      return `    ${simpleType(column.type)} ${identifier(column.name)}${marker}`;
    });
    blocks.push(`  ${identifier(table.name)} {\n${lines.join('\n')}\n  }`);

    for (const column of table.columns) {
      if (!column.fk) continue;
      const parent = column.fk.split('.')[0];
      if (names.has(parent) && parent !== table.name) relations.push(`  ${identifier(parent)} ||--o{ ${identifier(table.name)} : "${identifier(column.name)}"`);
    }
  }

  return ['erDiagram', ...relations, ...blocks].join('\n');
});
</script>

<template>
  <MermaidRender
    v-if="tables.length"
    :code="code"
  />
</template>
