<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { RepricingClientDetail } from '../../../interfaces';
import { formatReal, getI18nRouteName } from '@Helpers';
import { REPRICING_ROUTE_NAMES } from '../../../constants';
import { REPRICING_ITEM_KIND } from '../../../enums';

/*
 * One client of the repricing (rnc-1 R26, the demo's `rpDetail`): today's fee and the suggested
 * one, the yearly extra, the diagnostic items that justify it (a risk weighs more than an
 * opportunity) and "Gerar carta de reajuste", which opens the printable letter. Once marked, when
 * and by whom. The view loads the client and passes it down.
 */
interface Props {
  client: RepricingClientDetail | null;
  failed: boolean;
  loading: boolean;
}

const props = defineProps<Props>();

const { locale, t } = useI18n();

const markedLine = computed(() => {
  const mark = props.client?.repricing;

  if (!mark) return null;

  const values = {
    at: formatDay(mark.at),
    name: mark.by_name,
    pct: mark.pct,
    suggested: formatReal(mark.suggested_cents),
  };

  return mark.by_name ? t('repricing.detail.markedBy', values) : t('repricing.detail.markedAt', values);
});

// HELPERS
function formatDay(at: string): string {
  return new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    month: '2-digit',
    timeZone: 'America/Sao_Paulo',
    year: 'numeric',
  }).format(new Date(at));
}
</script>

<template>
  <section
    class="bg-panel shadow-lift flex min-w-0 flex-col gap-5 rounded-lg p-5 sm:p-6"
    data-testid="repricing-detail"
    :aria-busy="loading ? 'true' : 'false'"
    :data-client="client?.id"
  >
    <div
      v-if="loading"
      class="flex flex-col gap-3"
    >
      <Skeleton height="1.5rem" />

      <Skeleton height="5rem" />

      <Skeleton height="8rem" />
    </div>

    <p
      v-else-if="failed"
      class="text-body text-sm"
      role="alert"
    >
      {{ $t('repricing.detail.loadFailed') }}
    </p>

    <div
      v-else-if="!client"
      class="flex flex-col gap-2"
    >
      <h2 class="text-heading text-base font-semibold">{{ $t('repricing.detail.title') }}</h2>

      <p class="text-muted text-sm">{{ $t('repricing.detail.emptyText') }}</p>
    </div>

    <template v-else>
      <div class="flex flex-col gap-1">
        <h2 class="text-heading text-lg font-semibold break-words">{{ client.name }}</h2>

        <p class="text-muted text-sm">
          {{ $t(`repricing.sectors.${client.sector}`) }} · {{ $t(`repricing.regimes.${client.regime}`) }} ·
          {{ $t(`repricing.bands.${client.band}`) }} · {{ $t('repricing.detail.b2b', { value: client.b2b_share }) }}
        </p>
      </div>

      <div class="bg-panel-muted grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 rounded-md p-4">
        <div class="flex min-w-0 flex-col gap-1">
          <span class="text-muted text-xs font-medium">{{ $t('repricing.detail.today') }}</span>

          <strong class="text-heading text-lg font-semibold tabular-nums">{{
            formatReal(client.current_cents)
          }}</strong>
        </div>

        <i
          aria-hidden="true"
          class="pi pi-arrow-right text-subtle"
        />

        <div class="flex min-w-0 flex-col gap-1">
          <span class="text-muted text-xs font-medium">{{
            $t('repricing.detail.suggested', { pct: client.pct })
          }}</span>

          <strong class="text-primary-700 text-lg font-semibold tabular-nums">{{
            formatReal(client.suggested_cents)
          }}</strong>
        </div>
      </div>

      <p class="text-body text-sm">{{ $t('repricing.detail.perYear', { value: formatReal(client.uplift_cents) }) }}</p>

      <p
        v-if="markedLine"
        class="bg-success-100 text-success-800 rounded-md px-3 py-2 text-sm"
        data-testid="repricing-detail-marked"
      >
        {{ markedLine }}
      </p>

      <div class="flex flex-col gap-3">
        <h3 class="text-heading text-sm font-semibold">{{ $t('repricing.detail.items') }}</h3>

        <p
          v-if="client.items.length === 0"
          class="text-muted text-sm"
        >
          {{ $t('repricing.detail.noItems') }}
        </p>

        <ul
          v-else
          class="flex flex-col gap-2.5"
        >
          <li
            v-for="item in client.items"
            class="border-line flex flex-col gap-1 border-t pt-2.5 first:border-t-0 first:pt-0"
            data-testid="repricing-item"
            :key="item.id"
            :data-kind="item.kind"
          >
            <span class="flex flex-wrap items-center gap-2">
              <span
                :class="[
                  'rounded-sm px-1.5 py-0.5 text-xs font-semibold',
                  item.kind === REPRICING_ITEM_KIND.RISCO ? 'bg-warn-100 text-warn-800' : 'bg-info-100 text-info-800',
                ]"
              >
                {{ $t(`repricing.kinds.${item.kind}`) }}
              </span>

              <span class="text-heading text-sm font-medium">{{ item.title }}</span>
            </span>

            <span class="text-muted text-xs">{{ item.rule }}</span>
          </li>
        </ul>
      </div>

      <RouterLink
        class="bg-primary-500 text-primary-contrast hover:bg-primary-600 touch-target motion-settle inline-flex w-full items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition-colors sm:w-fit"
        data-testid="repricing-letter-link"
        :to="{ name: getI18nRouteName(REPRICING_ROUTE_NAMES.letter), params: { id: client.id } }"
      >
        <i
          aria-hidden="true"
          class="pi pi-file-edit"
        />

        {{ $t('repricing.detail.letter') }}
      </RouterLink>
    </template>
  </section>
</template>
