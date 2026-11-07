<script setup lang="ts">
import { computed } from 'vue';
import type { RepricingClient } from '../../../interfaces';
import { formatReal } from '@Helpers';

/*
 * The repricing list (rnc-1 R26): one row per client with today's fee, the suggested one, the uplift
 * and the yearly extra; a checkbox per row and one for every listed client feed the batch mark; the
 * name opens the client. The frame scrolls inside itself, both ways, so the page never does
 * (`w-0 min-w-full`: state.md Pitfalls) and the header stays in view over 500 rows.
 */
interface Props {
  clients: RepricingClient[];
  openId: number | null;
  selectedIds: number[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
  onOpen: [id: number];
  onToggle: [id: number];
  onToggleAll: [checked: boolean];
}>();

const allSelected = computed(() => props.clients.length > 0 && props.selectedIds.length === props.clients.length);
const someSelected = computed(() => props.selectedIds.length > 0 && !allSelected.value);

// EVENTS
function onToggleAll(event: Event) {
  emit('onToggleAll', (event.target as HTMLInputElement).checked);
}
</script>

<template>
  <div class="border-line max-h-[38rem] w-0 min-w-full overflow-auto rounded-md border">
    <table class="w-full min-w-[40rem] border-collapse text-sm">
      <caption class="sr-only">
        {{
          $t('repricing.table.caption')
        }}
      </caption>

      <thead class="bg-panel-muted sticky top-0 z-10">
        <tr>
          <th
            class="w-12 px-3 py-2.5"
            scope="col"
          >
            <label class="touch-target flex cursor-pointer items-center justify-center">
              <span class="sr-only">{{ $t('repricing.table.selectAll') }}</span>

              <input
                class="accent-primary-500 size-4 cursor-pointer"
                data-testid="repricing-select-all"
                type="checkbox"
                :checked="allSelected"
                :indeterminate="someSelected"
                @change="onToggleAll"
              />
            </label>
          </th>

          <th
            class="text-muted px-3 py-2.5 text-left text-xs font-semibold whitespace-nowrap"
            scope="col"
          >
            {{ $t('repricing.table.client') }}
          </th>

          <th
            v-for="key in ['current', 'suggested', 'pct', 'uplift']"
            class="text-muted px-3 py-2.5 text-right text-xs font-semibold whitespace-nowrap"
            :key
            scope="col"
          >
            {{ $t(`repricing.table.${key}`) }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="client in clients"
          data-testid="repricing-row"
          :key="client.id"
          :class="['border-line border-t', { 'bg-primary-50': client.id === openId }]"
          :data-current="client.current_cents"
          :data-id="client.id"
          :data-pct="client.pct"
          :data-regime="client.regime"
          :data-sector="client.sector"
          :data-suggested="client.suggested_cents"
        >
          <td class="px-3 py-2.5 text-center">
            <label class="touch-target flex cursor-pointer items-center justify-center">
              <span class="sr-only">{{ $t('repricing.table.selectOne', { name: client.name }) }}</span>

              <input
                class="accent-primary-500 size-4 cursor-pointer"
                data-testid="repricing-select"
                type="checkbox"
                :checked="selectedIds.includes(client.id)"
                @change="emit('onToggle', client.id)"
              />
            </label>
          </td>

          <td class="min-w-[14rem] px-3 py-2.5">
            <div class="flex flex-col gap-0.5">
              <button
                class="touch-target text-heading w-fit text-left font-semibold hover:underline"
                data-testid="repricing-open"
                type="button"
                :aria-label="$t('repricing.table.open', { name: client.name })"
                @click="emit('onOpen', client.id)"
              >
                {{ client.name }}
              </button>

              <span class="text-muted flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                <span
                  >{{ $t(`repricing.sectors.${client.sector}`) }} · {{ $t(`repricing.regimes.${client.regime}`) }}</span
                >

                <span
                  v-if="client.repricing"
                  class="bg-success-100 text-success-800 rounded-sm px-1.5 py-0.5 font-medium"
                  data-testid="repricing-marked"
                >
                  {{ $t('repricing.table.marked') }}
                </span>
              </span>
            </div>
          </td>

          <td class="text-body px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
            {{ formatReal(client.current_cents) }}
          </td>

          <td class="text-heading px-3 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
            {{ formatReal(client.suggested_cents) }}
          </td>

          <td class="text-primary-700 px-3 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
            +{{ client.pct }}%
          </td>

          <td class="text-body px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
            {{ formatReal(client.uplift_cents) }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
