<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import type { PartnerOption, Referral } from '../../interfaces';
import { usePaginatedList } from '@Composables';
import { formatReal, getI18nRouteName } from '@Helpers';
import { PIPELINE_ROUTE_NAMES } from '@PipelineModule';
import { TRAIL_PER_PAGE } from '../../constants';
import { GetPartnerOptionsService, GetReferralsService } from '../../services';

/*
 * The trail (rnc-1 R23, the demo's `governanceView()`): every referral of the network, newest
 * first — which company, to which partner, with which authorisation (the consent text as the lead
 * read it, and when), by whom and when, and where it stands — the status the partner moved it to
 * from its portal, its first-contact SLA chip and, once closed, the contract value (rnc-2 R10).
 * Filterable by partner, inactive ones included: the history keeps them. Reinaldo and the operator
 * only (`referrals.read`). `?parceiro=<id>` opens it already filtered — the briefing's "Cobrar
 * <parceiro>" lands here (rnc-3 F9, W83).
 */
const CLOSED_STATUS = 'fechado';

const route = useRoute();

const partnerId = ref<number | null>(partnerFromQuery(route.query.parceiro));
const partnerOptions = ref<PartnerOption[]>([]);

const { items, load, loadFailed, loading, onPage, page, perPage, total } = usePaginatedList<Referral>(
  (params) => GetReferralsService({ ...params, partnerId: partnerId.value }),
  { perPage: TRAIL_PER_PAGE }
);

onMounted(onOpen);

// HELPERS
/** A positive id from `?parceiro=`, or no filter: anything else in the address is ignored, not an error. */
function partnerFromQuery(value: unknown): number | null {
  const id = Number(Array.isArray(value) ? value[0] : value);

  return Number.isInteger(id) && id > 0 ? id : null;
}

function formatMoment(at: string): string {
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: '2-digit',
    timeZone: 'America/Sao_Paulo',
    year: 'numeric',
  }).format(new Date(at));
}

// EVENTS
function onOpen() {
  load();

  GetPartnerOptionsService()
    .then((response) => (partnerOptions.value = response.data))
    .catch(() => (partnerOptions.value = []));
}

function onFilter(value: number | null) {
  partnerId.value = value ?? null;
  page.value = 1;
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="partners.trail.title" />
    </template>

    <section
      class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5 lg:flex-row lg:items-end lg:justify-between"
    >
      <div class="flex min-w-0 flex-col gap-1.5">
        <p class="text-heading text-lg font-semibold">{{ $t('partners.trail.heading', total) }}</p>

        <p class="text-muted text-sm">{{ $t('partners.trail.lede') }}</p>
      </div>

      <div class="flex shrink-0 flex-col gap-2 lg:w-72">
        <label
          class="text-muted mb-2 block text-sm"
          for="trail-partner"
        >
          {{ $t('partners.trail.filterLabel') }}
        </label>

        <Select
          showClear
          class="w-full"
          inputId="trail-partner"
          optionLabel="label"
          optionValue="value"
          :modelValue="partnerId"
          :options="partnerOptions"
          :placeholder="$t('partners.trail.filterAll')"
          @update:modelValue="onFilter"
        >
          <template #option="{ option }">
            <span :class="{ 'text-muted': !option.active }">
              {{ option.active ? option.label : $t('partners.trail.inactiveOption', { name: option.label }) }}
            </span>
          </template>
        </Select>
      </div>
    </section>

    <div
      v-if="loading && items.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="7rem" />

      <Skeleton height="7rem" />
    </div>

    <div
      v-else-if="loadFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('partners.trail.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('partners.retry')"
        @click="load"
      />
    </div>

    <div
      v-else-if="items.length === 0"
      class="bg-panel shadow-lift flex flex-col items-center gap-2 rounded-lg p-8 text-center"
      data-testid="trail-empty"
    >
      <i
        aria-hidden="true"
        class="pi pi-shield text-subtle text-2xl"
      />

      <p class="text-heading font-semibold">{{ $t('partners.trail.emptyTitle') }}</p>

      <p class="text-muted text-sm">{{ $t('partners.trail.emptyText') }}</p>
    </div>

    <ol
      v-else
      class="flex min-w-0 flex-col gap-3"
      data-testid="trail"
    >
      <li
        v-for="referral in items"
        class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-5"
        data-testid="trail-row"
        :key="referral.id"
      >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex min-w-0 flex-col gap-1">
            <RouterLink
              class="text-heading w-fit font-semibold break-words hover:underline"
              data-testid="trail-company"
              :to="{ name: getI18nRouteName(PIPELINE_ROUTE_NAMES.lead), params: { id: referral.lead.id } }"
            >
              {{ referral.lead.company ?? $t('pipeline.anonymous') }}
            </RouterLink>

            <p
              class="text-body flex flex-wrap items-center gap-x-2 text-sm"
              data-testid="trail-partner"
            >
              <i
                aria-hidden="true"
                class="pi pi-arrow-right text-subtle text-xs"
              />

              <span class="font-medium">{{ referral.partner.name }}</span>

              <span class="text-muted">· {{ $t(`partners.specialties.${referral.partner.specialty}`) }}</span>

              <span
                v-if="!referral.partner.active"
                class="bg-panel-muted text-muted rounded-sm px-1.5 text-xs"
              >
                {{ $t('partners.inactive') }}
              </span>
            </p>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <span class="bg-primary-100 text-primary-950 rounded-sm px-2 py-0.5 text-xs font-semibold tabular-nums">
              {{ $t('partners.trail.score', { score: referral.score }) }}
            </span>

            <span
              class="bg-info-50 text-info-800 rounded-sm px-2 py-0.5 text-xs font-semibold"
              data-testid="trail-status"
            >
              {{ $t(`partners.statuses.${referral.status}`) }}
            </span>

            <span
              v-if="referral.status === CLOSED_STATUS && referral.deal_value_cents !== null"
              class="bg-success-50 text-success-800 rounded-sm px-2 py-0.5 text-xs font-semibold tabular-nums"
              data-testid="trail-value"
            >
              {{ $t('partners.trail.closedValue', { value: formatReal(referral.deal_value_cents) }) }}
            </span>
          </div>
        </div>

        <MSlaChip :sla="referral.sla" />

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div
            class="flex min-w-0 flex-col gap-1.5"
            data-testid="trail-consent"
          >
            <span class="text-success-800 flex items-center gap-1.5 text-xs font-semibold">
              <i
                aria-hidden="true"
                class="pi pi-check-circle"
              />

              {{
                $t('partners.trail.consentGiven', {
                  date: formatMoment(referral.consent.at),
                  version: referral.consent.version,
                })
              }}
            </span>

            <blockquote class="border-line text-body border-l-2 pl-3 text-xs break-words italic">
              “{{ referral.consent.text }}”
            </blockquote>
          </div>

          <div class="flex min-w-0 flex-col gap-1.5">
            <span
              class="text-muted text-xs"
              data-testid="trail-by"
            >
              <time
                class="tabular-nums"
                :datetime="referral.at"
              >
                {{ formatMoment(referral.at) }}
              </time>
              · {{ $t('partners.trail.by', { name: referral.referred_by.name }) }}
            </span>

            <p class="text-body text-sm break-words whitespace-pre-line">{{ referral.message }}</p>
          </div>
        </div>
      </li>
    </ol>

    <OPaginator
      v-if="total > perPage"
      :page
      :perPage
      :total
      @onPage="onPage"
    />
  </TheLayout>
</template>
