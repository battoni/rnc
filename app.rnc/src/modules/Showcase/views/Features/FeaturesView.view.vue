<script setup lang="ts">
import { computed, ref } from 'vue';
import CATALOGUE from './features.generated.json';

/*
 * The catalogue is a FACT, not a switch.
 *
 * Every row here is read from the per-unit metadata layer (`RULES.md` frontmatter +
 * `CHANGELOG.md`) by `app.rnc/scripts/build-features.mjs` and committed as JSON, because those
 * files are repository files and never reach the browser. Nothing on this screen turns a
 * feature on or off: in this architecture a feature is present because its code and its wiring
 * are in the repo, and a runtime flag would introduce a second state that can disagree with
 * the first one. If you want a feature gone, remove its wiring — and this screen will say so.
 *
 * In rnc every `origin` reads `rnc`, because rnc is the source. The field earns
 * its keep downstream, where it separates what a project received from what it wrote itself.
 */
const BASE_FEATURE = 'base';

const expanded = ref<string[]>([]);
const search = ref('');

const featureCount = computed(() => CATALOGUE.features.length);
const query = computed(() => search.value.trim().toLowerCase());

const toolingGroups = computed(() => {
  const groups = new Map<string, typeof CATALOGUE.tooling>();

  CATALOGUE.tooling.forEach((item) => {
    if (!groups.has(item.group)) groups.set(item.group, []);

    groups.get(item.group)?.push(item);
  });

  return [...groups.entries()].map(([group, items]) => ({ group, items }));
});

const features = computed(() =>
  CATALOGUE.features.filter((feature) => {
    if (!query.value) return true;

    const haystack = `${feature.name} ${feature.units.map((unit) => unit.path).join(' ')}`;

    return haystack.toLowerCase().includes(query.value);
  })
);

// HELPERS
function isExpanded(name: string) {
  return expanded.value.includes(name);
}

function sideLabel(side: string) {
  return side === 'api' ? 'features.side.api' : 'features.side.app';
}

// EVENTS
function onToggle(name: string) {
  if (isExpanded(name)) {
    expanded.value = expanded.value.filter((current) => current !== name);
    return;
  }

  expanded.value = [...expanded.value, name];
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-box"
        title="features.title"
      >
        <template #titleSuffix>
          <ATip tip-key="features.catalogue" />
        </template>
      </ThePageHeader>
    </template>

    <div class="flex flex-col gap-6">
      <div class="flex flex-col gap-2">
        <p class="text-body max-w-[70ch]">
          {{ $t('features.lede') }}
        </p>

        <p class="text-muted text-sm">
          {{ $t('features.tally', { features: featureCount, units: CATALOGUE.unitCount }) }}
        </p>
      </div>

      <InputText
        v-model="search"
        class="max-w-[420px]"
        :placeholder="$t('features.searchPlaceholder')"
      />

      <ul class="flex list-none flex-col gap-3 p-0">
        <li
          v-for="feature in features"
          class="bg-panel shadow-lift rounded-lg"
          :key="feature.name"
        >
          <button
            class="touch-target flex w-full items-center gap-3 p-4 text-left"
            type="button"
            @click="onToggle(feature.name)"
          >
            <i
              :class="['text-subtle text-sm', isExpanded(feature.name) ? 'pi pi-chevron-down' : 'pi pi-chevron-right']"
            />

            <span class="text-heading font-semibold">
              {{ feature.name === BASE_FEATURE ? $t('features.base') : feature.name }}
            </span>

            <span class="flex gap-1">
              <Tag
                v-for="side in feature.sides"
                :key="side"
                severity="secondary"
                :value="$t(sideLabel(side))"
              />
            </span>

            <span class="text-muted ml-auto text-sm">
              {{ $t('features.unitCount', { count: feature.unitCount }) }}
            </span>
          </button>

          <div
            v-if="isExpanded(feature.name)"
            class="border-line flex flex-col gap-2 border-t px-4 py-3"
          >
            <div
              v-for="unit in feature.units"
              class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm"
              :key="unit.path"
            >
              <code class="text-body">{{ unit.path }}</code>

              <span class="text-muted">{{ unit.version ?? '—' }}</span>

              <span class="text-muted">{{ $t('features.origin', { origin: unit.origin ?? '—' }) }}</span>

              <Tag
                v-if="!unit.mirrorsChangelog"
                severity="warn"
                :value="$t('features.versionDrift')"
              />
            </div>
          </div>
        </li>
      </ul>

      <p
        v-if="!features.length"
        class="text-muted py-6 text-center"
      >
        {{ $t('features.empty') }}
      </p>

      <!-- The tooling layer is NOT a list of units: `.claude/` and `cortex/` carry no
           RULES.md and no version, and giving each skill a metadata triad would be
           bureaucracy for its own sake. So a row here is a name and the sentence the thing
           already declares — frontmatter where there is frontmatter, the first heading
           where there is not. It is still part of what a project receives, which is why it
           belongs on a screen called Features. -->
      <section class="mt-4 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <h2 class="text-heading text-lg font-semibold">
            {{ $t('features.tooling.title') }}
          </h2>

          <p class="text-muted text-sm">
            {{ $t('features.tooling.lede', { count: CATALOGUE.toolingCount }) }}
          </p>
        </div>

        <div
          v-for="group in toolingGroups"
          class="bg-panel shadow-lift flex flex-col gap-2 rounded-lg p-4"
          :key="group.group"
        >
          <h3 class="text-muted font-mono text-xs tracking-wider uppercase">
            {{ $t(`features.tooling.groups.${group.group}`) }}
          </h3>

          <div
            v-for="item in group.items"
            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm"
            :key="item.path"
          >
            <code class="text-body">{{ item.name }}</code>

            <span class="text-muted">{{ item.description ?? '—' }}</span>
          </div>
        </div>
      </section>
    </div>
  </TheLayout>
</template>
