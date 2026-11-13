<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { NetworkLedger } from '../../interfaces';
import { formatReal } from '@Helpers';
import { LEDGER_MODEL, useLedgerMonth } from '@PortalModule';
import { useUserStore } from '@UserModule';
import { PARTNER_PERMISSIONS } from '../../constants';
import { GetLedgerService, UpdateLedgerModelService } from '../../services';

/*
 * Reinaldo's network ledger (rnc-2 R11, D10; the demo's ledger seen from Franco's side): every
 * partner's month — closings, the commission, whether its access billed the fee, what it owes now
 * — the total under the network's one model and what the other model would give. The switch
 * (`partners.manage`) moves the whole network, then both totals are read again. Every figure is
 * "a conferir": nothing is billed.
 */
const BASIS_POINTS_PER_PERCENT = 100;
const MODELS: LEDGER_MODEL[] = [LEDGER_MODEL.COMISSAO, LEDGER_MODEL.ASSINATURA];
const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const { t } = useI18n();
const { isLatest, month, monthLabel, settle, shift } = useLedgerMonth();
const toast = useToast();

const hasFailed = ref(false);
const isLoading = ref(true);
const ledger = ref<NetworkLedger | null>(null);
const switchingTo = ref<LEDGER_MODEL | null>(null);

const canSwitch = computed(() => userStore.hasPermission(PARTNER_PERMISSIONS.manage));
const rate = computed(() => (ledger.value?.commission_rate_bp ?? 0) / BASIS_POINTS_PER_PERCENT);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetLedgerService(month.value)
    .then((response) => {
      ledger.value = response.data;
      settle(response.data.month);
    })
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

// EVENTS
function onShift(by: number) {
  if (!month.value) return;

  shift(by);
  load();
}

function onSwitch(model: LEDGER_MODEL) {
  const isNoMove = model === ledger.value?.model || switchingTo.value !== null;

  if (isNoMove) return;

  switchingTo.value = model;

  UpdateLedgerModelService(model)
    .then(load)
    .catch(() => toast.add({ life: TOAST_LIFE_MS, severity: 'warn', summary: t('partners.ledger.switchFailed') }))
    .finally(() => (switchingTo.value = null));
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="partners.ledger.title" />
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-4 rounded-lg p-5">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 flex-col gap-1.5">
          <p class="text-muted text-sm">{{ $t('partners.ledger.lede') }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button
            text
            icon="pi pi-chevron-left"
            severity="secondary"
            :aria-label="$t('partners.ledger.previousMonth')"
            :disabled="isLoading"
            @click="onShift(-1)"
          />

          <span
            aria-live="polite"
            class="text-heading min-w-36 text-center font-medium tabular-nums"
            data-testid="ledger-month"
          >
            {{ monthLabel }}
          </span>

          <Button
            text
            icon="pi pi-chevron-right"
            severity="secondary"
            :aria-label="$t('partners.ledger.nextMonth')"
            :disabled="isLoading || isLatest"
            @click="onShift(1)"
          />
        </div>
      </div>

      <div
        v-if="ledger && canSwitch"
        class="flex flex-wrap gap-2"
        role="group"
        :aria-label="$t('partners.ledger.modelLabel')"
      >
        <Button
          v-for="option in MODELS"
          :key="option"
          size="small"
          :aria-pressed="option === ledger.model ? 'true' : 'false'"
          :label="$t(`partners.ledger.models.${option}`)"
          :loading="switchingTo === option"
          :outlined="option !== ledger.model"
          :severity="option === ledger.model ? 'primary' : 'secondary'"
          @click="onSwitch(option)"
        />
      </div>

      <p
        v-else-if="ledger"
        class="text-body text-sm"
        data-testid="ledger-model"
      >
        {{ $t('partners.ledger.currentModel', { model: $t(`partners.ledger.models.${ledger.model}`) }) }}
      </p>

      <p
        v-if="ledger"
        class="text-muted text-sm"
      >
        {{
          ledger.model === LEDGER_MODEL.COMISSAO
            ? $t('partners.ledger.ruleCommission', { rate })
            : $t('partners.ledger.ruleSubscription', { fee: formatReal(ledger.monthly_fee_cents) })
        }}
        {{ $t('partners.ledger.networkWide') }}
      </p>

      <p
        class="bg-warn-50 text-warn-800 flex items-center gap-2 rounded-sm px-3 py-2 text-sm"
        data-testid="ledger-notice"
      >
        <i
          aria-hidden="true"
          class="pi pi-info-circle"
        />

        {{ $t('partners.ledger.notice') }}
      </p>
    </section>

    <Skeleton
      v-if="isLoading && !ledger"
      height="14rem"
    />

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('partners.ledger.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('partners.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="ledger">
      <section
        class="grid grid-cols-1 gap-3 md:grid-cols-3"
        :aria-label="$t('partners.ledger.summaryLabel')"
      >
        <div class="bg-panel shadow-lift motion-enter flex flex-col gap-1 rounded-lg p-4">
          <span class="text-muted text-sm">
            {{ $t('partners.ledger.total', { model: $t(`partners.ledger.models.${ledger.model}`) }) }}
          </span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="ledger-total"
          >
            {{ formatReal(ledger.total_cents) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 1 }"
        >
          <span class="text-muted text-sm">
            {{ $t('partners.ledger.other', { model: $t(`partners.ledger.models.${ledger.other.model}`) }) }}
          </span>

          <strong
            class="text-body text-2xl font-semibold tabular-nums"
            data-testid="ledger-other"
          >
            {{ formatReal(ledger.other.total_cents) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 2 }"
        >
          <span class="text-muted text-sm">{{ $t('partners.ledger.dealsLabel') }}</span>

          <strong class="text-heading text-2xl font-semibold tabular-nums">{{ ledger.deals }}</strong>

          <span class="text-muted text-xs tabular-nums">
            {{ $t('partners.ledger.dealTotal', { total: formatReal(ledger.deal_total_cents) }) }}
          </span>
        </div>
      </section>

      <section class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5">
        <p
          v-if="ledger.partners.length === 0"
          class="text-muted py-4 text-center text-sm"
        >
          {{ $t('partners.ledger.empty') }}
        </p>

        <div
          v-else
          class="w-0 min-w-full overflow-x-auto"
        >
          <table class="w-full text-sm">
            <thead>
              <tr class="text-muted border-line border-b text-left">
                <th
                  class="py-2 pr-4 font-medium"
                  scope="col"
                >
                  {{ $t('partners.ledger.partner') }}
                </th>

                <th
                  class="py-2 pr-4 text-right font-medium"
                  scope="col"
                >
                  {{ $t('partners.ledger.deals') }}
                </th>

                <th
                  class="py-2 pr-4 text-right font-medium"
                  scope="col"
                >
                  {{ $t('partners.ledger.dealValue') }}
                </th>

                <th
                  class="py-2 pr-4 text-right font-medium"
                  scope="col"
                >
                  {{ $t('partners.ledger.commission', { rate }) }}
                </th>

                <th
                  class="py-2 pr-4 text-right font-medium"
                  scope="col"
                >
                  {{ $t('partners.ledger.subscription') }}
                </th>

                <th
                  class="py-2 text-right font-medium"
                  scope="col"
                >
                  {{ $t('partners.ledger.due') }}
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="row in ledger.partners"
                class="border-line border-b last:border-b-0"
                data-testid="ledger-row"
                :key="row.partner.id"
              >
                <td class="py-2 pr-4">
                  <span class="text-heading font-medium break-words">{{ row.partner.name }}</span>

                  <span
                    v-if="!row.partner.active"
                    class="bg-panel-muted text-muted ml-2 rounded-sm px-1.5 text-xs"
                  >
                    {{ $t('partners.inactive') }}
                  </span>
                </td>

                <td class="text-body py-2 pr-4 text-right tabular-nums">{{ row.deals }}</td>

                <td class="text-body py-2 pr-4 text-right tabular-nums">{{ formatReal(row.deal_total_cents) }}</td>

                <td class="text-body py-2 pr-4 text-right tabular-nums">{{ formatReal(row.commission_cents) }}</td>

                <td class="text-body py-2 pr-4 text-right tabular-nums">
                  {{ row.subscribed ? formatReal(row.subscription_cents) : $t('partners.ledger.noAccess') }}
                </td>

                <td class="text-heading py-2 text-right font-semibold tabular-nums">{{ formatReal(row.due_cents) }}</td>
              </tr>
            </tbody>

            <tfoot>
              <tr class="border-line border-t">
                <th
                  class="text-heading py-3 pr-4 text-left font-semibold"
                  colspan="5"
                  scope="row"
                >
                  {{ $t('partners.ledger.footerTotal') }}
                </th>

                <td class="text-heading py-3 text-right text-base font-semibold tabular-nums">
                  {{ formatReal(ledger.total_cents) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </template>
  </TheLayout>
</template>
