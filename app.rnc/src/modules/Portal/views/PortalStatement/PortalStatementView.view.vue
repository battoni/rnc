<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { PortalStatement, StatementLine } from '../../interfaces';
import { formatReal } from '@Helpers';
import { useLedgerMonth } from '../../composables';
import { LEDGER_MODEL, STATEMENT_LINE_KIND } from '../../enums';
import { GetPortalStatementService } from '../../services';

/*
 * The partner's statement (rnc-2 R11, the demo's `partnerLedgerView`): the month's closings, what
 * each adds under the firm's money model, and "Total devido à Franco" — every figure "a conferir",
 * nothing is billed. Only the current model: the comparison is Franco's negotiation (D10), so this
 * screen never names the other one. The API decides the partner (its session) and "this month".
 */
const PORTAL_TIME_ZONE = 'America/Sao_Paulo';
const BASIS_POINTS_PER_PERCENT = 100;

const { isLatest, month, monthLabel, settle, shift } = useLedgerMonth();

const hasFailed = ref(false);
const isLoading = ref(true);
const statement = ref<PortalStatement | null>(null);

const isCommission = computed(() => statement.value?.model === LEDGER_MODEL.COMISSAO);

const rule = computed(() =>
  isCommission.value
    ? {
        key: 'portal.statement.ruleCommission',
        params: { rate: (statement.value?.commission_rate_bp ?? 0) / BASIS_POINTS_PER_PERCENT },
      }
    : { key: 'portal.statement.ruleSubscription', params: { fee: formatReal(statement.value?.monthly_fee_cents ?? 0) } }
);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetPortalStatementService(month.value)
    .then((response) => {
      statement.value = response.data;
      settle(response.data.month);
    })
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function formatDay(iso: string): string {
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    timeZone: PORTAL_TIME_ZONE,
    year: 'numeric',
  }).format(new Date(iso));
}

function isSubscription(line: StatementLine): boolean {
  return line.kind === STATEMENT_LINE_KIND.SUBSCRIPTION;
}

// EVENTS
function onShift(by: number) {
  if (!month.value) return;

  shift(by);
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="portal.statement.title" />
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-4 rounded-lg p-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-heading text-lg font-semibold break-words">
          {{ $t('portal.statement.heading', { firm: statement?.firm.name ?? $t('portal.firmFallback') }) }}
        </h2>

        <div class="flex items-center gap-1">
          <Button
            text
            icon="pi pi-chevron-left"
            severity="secondary"
            :aria-label="$t('portal.statement.previousMonth')"
            :disabled="isLoading"
            @click="onShift(-1)"
          />

          <span
            aria-live="polite"
            class="text-heading min-w-36 text-center font-medium tabular-nums"
            data-testid="statement-month"
          >
            {{ monthLabel }}
          </span>

          <Button
            text
            icon="pi pi-chevron-right"
            severity="secondary"
            :aria-label="$t('portal.statement.nextMonth')"
            :disabled="isLoading || isLatest"
            @click="onShift(1)"
          />
        </div>
      </div>

      <p
        class="bg-warn-50 text-warn-800 flex items-center gap-2 rounded-sm px-3 py-2 text-sm"
        data-testid="ledger-notice"
      >
        <i
          aria-hidden="true"
          class="pi pi-info-circle"
        />

        {{ $t('portal.statement.notice') }}
      </p>

      <p
        v-if="statement"
        class="text-muted text-sm"
        data-testid="statement-rule"
      >
        {{ $t(rule.key, rule.params) }}
      </p>
    </section>

    <Skeleton
      v-if="isLoading && !statement"
      height="12rem"
    />

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('portal.statement.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('portal.retry')"
        @click="load"
      />
    </div>

    <section
      v-else-if="statement"
      class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5"
    >
      <div class="w-0 min-w-full overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-muted border-line border-b text-left">
              <th
                class="py-2 pr-4 font-medium"
                scope="col"
              >
                {{ $t('portal.statement.item') }}
              </th>

              <th
                class="py-2 pr-4 font-medium"
                scope="col"
              >
                {{ $t('portal.statement.closedAt') }}
              </th>

              <th
                class="py-2 pr-4 text-right font-medium"
                scope="col"
              >
                {{ $t('portal.statement.dealValue') }}
              </th>

              <th
                class="py-2 text-right font-medium"
                scope="col"
              >
                {{ $t('portal.statement.owed', { firm: statement.firm.name }) }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="line in statement.lines"
              class="border-line border-b last:border-b-0"
              data-testid="statement-line"
              :key="line.referral_id ?? line.kind"
            >
              <td class="text-heading py-2 pr-4 break-words">
                {{
                  isSubscription(line)
                    ? $t('portal.statement.subscriptionLine')
                    : (line.company ?? $t('pipeline.anonymous'))
                }}
              </td>

              <td class="text-body py-2 pr-4 tabular-nums">
                {{ line.closed_at ? formatDay(line.closed_at) : $t('portal.statement.thisMonth') }}
              </td>

              <td class="text-body py-2 pr-4 text-right tabular-nums">
                {{ line.deal_value_cents === null ? '—' : formatReal(line.deal_value_cents) }}
              </td>

              <td class="text-heading py-2 text-right font-medium tabular-nums">
                {{
                  line.amount_cents > 0 || isSubscription(line)
                    ? formatReal(line.amount_cents)
                    : $t('portal.statement.included')
                }}
              </td>
            </tr>

            <tr v-if="statement.lines.length === 0">
              <td
                class="text-muted py-4 text-center"
                colspan="4"
              >
                {{ $t('portal.statement.empty') }}
              </td>
            </tr>
          </tbody>

          <tfoot>
            <tr class="border-line border-t">
              <th
                class="text-heading py-3 pr-4 text-left font-semibold"
                colspan="3"
                scope="row"
              >
                {{ $t('portal.statement.total', { firm: statement.firm.name }) }}
              </th>

              <td
                class="text-heading py-3 text-right text-base font-semibold tabular-nums"
                data-testid="statement-total"
              >
                {{ formatReal(statement.total_cents) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <p class="text-muted text-sm">
        {{
          $t(
            'portal.statement.deals',
            { total: formatReal(statement.deal_total_cents), n: statement.deals },
            statement.deals
          )
        }}
      </p>
    </section>
  </TheLayout>
</template>
