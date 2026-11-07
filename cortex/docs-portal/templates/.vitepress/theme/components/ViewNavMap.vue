<script setup lang="ts">
import views from '../../data/views.json';
</script>

<template>
  <div class="view-nav-map">
    <p class="source">
      {{ views.views.length }} views · generated from <code>{{ views.generatedFrom }}</code>
    </p>

    <table>
      <thead>
        <tr>
          <th>Route name</th>
          <th>Component</th>
          <th>Path(s)</th>
          <th>Area</th>
          <th>Reached via</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="view in views.views"
          :key="view.routeName"
        >
          <td><code>{{ view.routeName }}</code></td>
          <td><code>{{ view.component }}</code></td>
          <td>
            <code
              v-for="path in view.paths"
              :key="path"
              class="path"
            >{{ path }}</code>
          </td>
          <td>{{ view.module }}</td>
          <td>
            {{ (views.reachability.find((r) => r.routeName === view.routeName) || {}).reachedVia || 'hub/parent' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.view-nav-map {
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

.path {
  display: inline-block;
  margin: 1px 0;
}
</style>
