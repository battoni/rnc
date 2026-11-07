<script setup lang="ts">
import { computed } from 'vue';
import views from '../../data/views.json';

/*
 * Every screen, grouped by HOW a person reaches it. The idea it makes visible: no screen is an
 * orphan. A screen reached only from another screen is a hub child; one with no path at all would
 * show up here with nothing to group it under — which is the bug.
 */
type Reach = { view: string; routeName: string; reachedVia: string; gate: string | null; status: string };

const groups = computed(() => {
  const reachability = views.reachability as Reach[];

  return [
    { key: 'nav', title: 'Navigation', hint: 'A menu entry opens it', items: reachability.filter((item) => item.reachedVia === 'nav') },
    { key: 'staff', title: 'Staff navigation', hint: 'A menu entry only staff see', items: reachability.filter((item) => item.reachedVia === 'nav:staff') },
    { key: 'hub', title: 'From another screen', hint: 'Opened by an action on a parent screen', items: reachability.filter((item) => !item.reachedVia.startsWith('nav')) },
  ].filter((group) => group.items.length);
});
</script>

<template>
  <div class="reachability-map">
    <section
      v-for="group in groups"
      :key="group.key"
      class="reachability-group"
    >
      <header class="reachability-head">
        <span class="reachability-title">{{ group.title }}</span>

        <span class="reachability-hint">{{ group.hint }}</span>
      </header>

      <div class="reachability-cards">
        <div
          v-for="item in group.items"
          :key="item.routeName"
          :class="['reachability-card', { planned: item.status === 'planned' }]"
        >
          <span class="reachability-view">{{ item.view }}</span>

          <div class="reachability-meta">
            <span
              v-if="item.gate"
              class="reachability-gate"
            >
              {{ item.gate }}
            </span>

            <span
              v-if="item.status === 'planned'"
              class="reachability-planned"
            >
              Planned
            </span>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.reachability-map {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin: 20px 0;
}

.reachability-group {
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-divider);
  border-left: 4px solid var(--vp-c-brand-1);
  border-radius: 10px;
  padding: 14px 16px;
}

.reachability-head {
  align-items: baseline;
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}

.reachability-title {
  color: var(--vp-c-text-1);
  font-weight: 700;
}

.reachability-hint {
  color: var(--vp-c-text-3);
  font-size: 12px;
}

.reachability-cards {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.reachability-card {
  background: var(--vp-c-bg);
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
}

.reachability-card.planned {
  border-style: dashed;
  opacity: 0.78;
}

.reachability-view {
  color: var(--vp-c-text-1);
  font-family: var(--vp-font-family-mono);
  font-size: 13px;
  font-weight: 600;
  word-break: break-word;
}

.reachability-meta {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.reachability-gate,
.reachability-planned {
  border-radius: 999px;
  font-size: 11px;
  padding: 1px 7px;
}

.reachability-gate {
  background: var(--vp-c-brand-soft);
  color: var(--vp-c-brand-1);
  font-family: var(--vp-font-family-mono);
}

.reachability-planned {
  background: var(--vp-c-warning-soft);
  color: var(--vp-c-warning-1);
  font-weight: 600;
}
</style>
