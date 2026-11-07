<script setup lang="ts">
import type { ReadinessClient } from '../../../interfaces';
import { useFiscalFormat } from '../../../composables';
import { READINESS_STATUS } from '../../../enums';

/*
 * The readiness list (rnc-2 R18, the demo's `nfeView` table): one row per client — its name opens
 * it, its regime and emitting system under it, the window's issued invoices, the % complete with
 * a bar in the status's tone, the invoices at risk of rejection 1115 and the status. A client with
 * no imported sales reads "—", never a 0 %. The frame scrolls inside itself, both ways, so the page
 * never does (`w-0 min-w-full`), and the header stays in view over a long portfolio.
 */
interface Props {
  clients: ReadinessClient[];
  openId: number | null;
}

defineProps<Props>();

const emit = defineEmits<{
  onOpen: [id: number];
}>();

const FILL_CLASSES: Record<READINESS_STATUS, string> = {
  [READINESS_STATUS.ATENCAO]: 'bg-warn-500',
  [READINESS_STATUS.CRITICO]: 'bg-danger-600',
  [READINESS_STATUS.OK]: 'bg-success-600',
  [READINESS_STATUS.SEM_NOTAS]: 'bg-panel-muted',
};

const { formatCount, formatPercent } = useFiscalFormat();
</script>

<template>
  <div class="border-line max-h-[38rem] w-0 min-w-full overflow-auto rounded-md border">
    <table class="w-full min-w-[40rem] border-collapse text-sm">
      <caption class="sr-only">
        {{
          $t('fiscal.readiness.table.caption')
        }}
      </caption>

      <thead class="bg-panel-muted sticky top-0 z-10">
        <tr>
          <th
            class="text-muted px-3 py-2.5 text-left text-xs font-semibold whitespace-nowrap"
            scope="col"
          >
            {{ $t('fiscal.readiness.table.client') }}
          </th>

          <th
            v-for="key in ['invoices', 'complete', 'atRisk']"
            class="text-muted px-3 py-2.5 text-right text-xs font-semibold whitespace-nowrap"
            :key
            scope="col"
          >
            {{ $t(`fiscal.readiness.table.${key}`) }}
          </th>

          <th
            class="text-muted px-3 py-2.5 text-left text-xs font-semibold whitespace-nowrap"
            scope="col"
          >
            {{ $t('fiscal.readiness.table.status') }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="client in clients"
          data-testid="readiness-row"
          :key="client.id"
          :class="['border-line border-t', { 'bg-primary-50': client.id === openId }]"
          :data-id="client.id"
          :data-status="client.status"
        >
          <td class="min-w-[14rem] px-3 py-2.5">
            <div class="flex flex-col gap-0.5">
              <button
                class="touch-target text-heading w-fit text-left font-semibold hover:underline"
                data-testid="readiness-open"
                type="button"
                :aria-label="$t('fiscal.readiness.table.open', { name: client.name })"
                @click="emit('onOpen', client.id)"
              >
                {{ client.name }}
              </button>

              <span class="text-muted text-xs">
                {{ $t(`fiscal.regimes.${client.regime}`) }} ·
                {{ client.system ?? $t('fiscal.readiness.table.noSystem') }}
              </span>
            </div>
          </td>

          <td class="text-body px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
            {{ formatCount(client.invoices) }}
          </td>

          <td
            class="px-3 py-2.5 text-right whitespace-nowrap"
            data-testid="readiness-pct"
          >
            <span class="inline-flex items-center justify-end gap-2">
              <span
                v-if="client.complete_pct !== null"
                aria-hidden="true"
                class="bg-panel-muted h-1.5 w-16 overflow-hidden rounded-sm"
              >
                <span
                  :class="['motion-wipe-y block h-full rounded-sm', FILL_CLASSES[client.status]]"
                  :style="{ width: `${client.complete_pct}%` }"
                />
              </span>

              <span class="text-body tabular-nums">{{ formatPercent(client.complete_pct) }}</span>
            </span>
          </td>

          <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
            <strong
              v-if="client.at_risk > 0"
              class="bg-danger-50 text-danger-800 rounded-sm px-1.5 py-0.5 font-semibold"
            >
              {{ formatCount(client.at_risk) }}
            </strong>

            <span
              v-else
              class="text-muted"
            >
              0
            </span>
          </td>

          <td class="px-3 py-2.5">
            <MReadinessStatusChip :status="client.status" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
