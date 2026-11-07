<script setup lang="ts">
import { computed } from 'vue';
import endpoints from '../../data/endpoints.json';

const props = defineProps<{ module?: string }>();

const rows = computed(() =>
  endpoints.endpoints.filter((endpoint) => !props.module || endpoint.module === props.module),
);

const modules = computed(() => endpoints.modules);

function methodClass(verb: string): string {
  return `verb verb-${verb.toLowerCase()}`;
}
</script>

<template>
  <div class="endpoint-table">
    <p
      v-if="!props.module"
      class="source"
    >
      {{ rows.length }} endpoints across {{ modules.length }} areas · generated from
      <code>{{ endpoints.generatedFrom }}</code>
    </p>

    <table>
      <thead>
        <tr>
          <th>Method</th>
          <th>Path</th>
          <th>Handler</th>
          <th>Access</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="endpoint in rows"
          :key="endpoint.verb + endpoint.path"
        >
          <td><span :class="methodClass(endpoint.verb)">{{ endpoint.verb }}</span></td>
          <td><code>{{ endpoint.path }}</code></td>
          <td><code>{{ endpoint.controller }}@{{ endpoint.action }}</code></td>
          <td>
            <span v-if="endpoint.permission">🔑 {{ endpoint.permission }}</span>
            <span v-else-if="endpoint.auth">🔒 auth</span>
            <span v-else>🌐 public</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.endpoint-table {
  overflow-x: auto;
}

.source {
  font-size: 13px;
  color: var(--vp-c-text-2);
}

table {
  width: 100%;
  display: table;
}

.verb {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  color: #fff;
}

.verb-get {
  background: #3b7d3b;
}

.verb-post {
  background: #2f6fb0;
}

.verb-put,
.verb-patch {
  background: #b07a2f;
}

.verb-delete {
  background: #b0402f;
}
</style>
