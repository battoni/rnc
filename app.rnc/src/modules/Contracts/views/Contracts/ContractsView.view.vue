<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { ContractAlert, ContractAlertRow, ContractImportResult, LegalPartner } from '../../interfaces';
import { formatReal } from '@Helpers';
import { GetContractsService, GetLegalPartnersService, MarkContractClauseService } from '../../services';

/*
 * "Alerta de contratos" (rnc-3 R18–R22, the demo's `contractsView`): the clients' contracts that
 * cross 2027–2033 without a Reforma price clause, most exposed first. A note with the dated
 * statement (facts.md §2, "Everyone"), four KPIs — "Sem cláusula da Reforma N de M", the annual
 * value, value × years exposed and those sent to legal —, then one row per at-risk contract
 * (`MContractRow`). Every figure is the API's: after a referral, a clause or an import the alert is
 * read again rather than patched here (as the Radar and the ledger, W41).
 */
const TOAST_LIFE_MS = 4000;
const { t } = useI18n();
const toast = useToast();

const alert = ref<ContractAlert | null>(null);
const dialogVisible = ref(false);
const loadFailed = ref(false);
const loading = ref(false);
const markingId = ref<number | null>(null);
const partners = ref<LegalPartner[]>([]);

let partnersRequest: Promise<void> | null = null;

const topExposureCents = computed(() => alert.value?.contracts[0]?.exposure_cents ?? 0);

onMounted(load);

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;

  Promise.all([GetContractsService(), loadPartnersOnce()])
    .then(([response]) => (alert.value = response.data))
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

/**
 * The picker's partners, read once with the first alert so a row never flashes "Sem parceiro
 * jurídico na rede." before they arrive; a failure leaves the list empty and says exactly that.
 */
function loadPartnersOnce(): Promise<void> {
  partnersRequest ??= GetLegalPartnersService()
    .then((response) => {
      partners.value = response.data;
    })
    .catch(() => {
      partners.value = [];
    });

  return partnersRequest;
}

function markClause(contract: ContractAlertRow) {
  markingId.value = contract.id;

  MarkContractClauseService(contract.id)
    .then(() => {
      toast.add({
        life: TOAST_LIFE_MS,
        severity: 'success',
        summary: t('contracts.clauseToast', { client: contract.client.name }),
      });
      load();
    })
    .catch(() => toast.add({ life: TOAST_LIFE_MS, severity: 'warn', summary: t('contracts.row.clauseFailed') }))
    .finally(() => (markingId.value = null));
}

// EVENTS

function onImported(result: ContractImportResult) {
  load();

  if (result.total_errors > 0) return;

  dialogVisible.value = false;
  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('contracts.import.success', { ...result }) });
}

function onSent(contract: ContractAlertRow, partner: LegalPartner) {
  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('contracts.sentToast', { client: contract.client.name, partner: partner.name }),
  });
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="contracts.title">
        <template
          v-if="!dialogVisible"
          #actions
        >
          <Button
            data-testid="contracts-import"
            icon="pi pi-upload"
            severity="secondary"
            :label="$t('contracts.import.action')"
            @click="dialogVisible = true"
          />
        </template>
      </ThePageHeader>
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-3 rounded-lg p-5">
      <div class="flex flex-col gap-1.5">
        <h2 class="text-heading text-lg font-semibold">{{ $t('contracts.heading') }}</h2>

        <p class="text-muted text-sm">{{ $t('contracts.lede') }}</p>
      </div>

      <p
        class="bg-info-50 text-info-800 flex items-start gap-2 rounded-md px-3 py-2 text-sm"
        data-testid="contracts-note"
      >
        <i
          aria-hidden="true"
          class="pi pi-info-circle mt-0.5"
        />

        {{ $t('contracts.note') }}
      </p>
    </section>

    <div
      v-if="loading && !alert"
      class="flex flex-col gap-3"
    >
      <Skeleton height="7rem" />

      <Skeleton height="24rem" />
    </div>

    <div
      v-else-if="loadFailed && !alert"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('contracts.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('contracts.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="alert">
      <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
        :aria-label="$t('contracts.kpis.label')"
      >
        <div class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4">
          <span class="text-muted text-sm">{{ $t('contracts.kpis.atRisk') }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="contracts-at-risk"
            :data-value="alert.summary.at_risk"
          >
            {{ $t('contracts.kpis.atRiskValue', { count: alert.summary.at_risk, total: alert.summary.total }) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 1 }"
        >
          <span class="text-muted text-sm">{{ $t('contracts.kpis.value') }}</span>

          <strong
            class="text-heading text-xl font-semibold break-words tabular-nums"
            data-testid="contracts-value"
            :data-value="alert.summary.annual_value_cents"
          >
            {{ formatReal(alert.summary.annual_value_cents) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 2 }"
        >
          <span class="text-muted text-sm">{{ $t('contracts.kpis.exposure') }}</span>

          <strong
            class="text-heading text-xl font-semibold break-words tabular-nums"
            data-testid="contracts-exposure"
            :data-value="alert.summary.exposure_cents"
          >
            {{ formatReal(alert.summary.exposure_cents) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 3 }"
        >
          <span class="text-muted text-sm">{{ $t('contracts.kpis.referred') }}</span>

          <strong
            v-count-up
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="contracts-referred"
            :data-value="alert.summary.referred"
          >
            {{ alert.summary.referred }}
          </strong>
        </div>
      </section>

      <section
        :aria-label="$t('contracts.list.title')"
        :class="[
          'bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-4 rounded-lg p-5',
          { 'opacity-60 transition-opacity': loading },
        ]"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-heading text-base font-semibold">{{ $t('contracts.list.title') }}</h2>

          <p class="text-muted text-sm">{{ $t('contracts.list.lede') }}</p>
        </div>

        <p
          v-if="alert.contracts.length === 0"
          class="text-muted py-4 text-center text-sm"
          data-testid="contracts-empty"
        >
          {{ $t('contracts.list.empty') }}
        </p>

        <ul
          v-else
          class="flex min-w-0 flex-col"
          data-testid="contracts-list"
        >
          <MContractRow
            v-for="contract in alert.contracts"
            :key="contract.id"
            :contract
            :isMarking="markingId === contract.id"
            :partners
            :topExposureCents
            @onClause="markClause"
            @onSent="onSent"
            @onStale="load"
          />
        </ul>
      </section>
    </template>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        title="contracts.import.title"
      >
        <MImportContractsForm
          @onClose="dialogVisible = false"
          @onImported="onImported"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
