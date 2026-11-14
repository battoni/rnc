<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ViewAsSession } from '../../interfaces';
import { usePaginatedList } from '@Composables';
import { GetViewAsLogService } from '../../services';

/*
 * The view-as log (rnc-4 R26, A42/A43): every time the operator entered a firm as support — who, which
 * firm, from which address (C18), when it started and ended, and how (`sair`, `logout`, `expirou`; open
 * while none) — latest first, 25 a page as the API pages it. A failed load is not an empty log.
 */
const PER_PAGE = 25;
const DATE_FORMAT: Intl.DateTimeFormatOptions = { dateStyle: 'short', timeStyle: 'short' };

const { locale, t } = useI18n();

const { items, load, loadFailed, loading, onPage, page, perPage, total } = usePaginatedList<ViewAsSession>(
  (params) => GetViewAsLogService(params),
  { perPage: PER_PAGE }
);

const dateFormat = computed(() => new Intl.DateTimeFormat(locale.value, DATE_FORMAT));

onMounted(load);

// HELPERS
function dateOf(value: string): string {
  return dateFormat.value.format(new Date(value));
}

function reasonOf(session: ViewAsSession): string {
  return t(`platform.viewAs.log.reasons.${session.end_reason ?? 'open'}`);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-eye"
        title="platform.viewAs.log.title"
      />
    </template>

    <p class="text-body text-sm">{{ $t('platform.viewAs.log.lede') }}</p>

    <div
      v-if="loading && items.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="4rem" />

      <Skeleton height="4rem" />
    </div>

    <div
      v-else-if="loadFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('platform.viewAs.log.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('platform.viewAs.log.retry')"
        @click="load"
      />
    </div>

    <p
      v-else-if="items.length === 0"
      class="bg-panel shadow-lift text-muted rounded-lg p-8 text-center"
      data-testid="view-as-log-empty"
    >
      {{ $t('platform.viewAs.log.empty') }}
    </p>

    <ul
      v-else
      class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg"
    >
      <li
        v-for="session in items"
        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
        data-testid="view-as-log-row"
        :key="session.id"
      >
        <div class="flex min-w-0 flex-col gap-1">
          <span class="text-heading font-semibold break-words">{{ session.firm?.name }}</span>

          <span class="text-muted flex flex-wrap gap-x-3 gap-y-1 text-xs">
            <span>{{ session.operator?.name }}</span>

            <span
              v-if="session.ip_address"
              class="tabular-nums"
            >
              {{ $t('platform.viewAs.log.address', { ip: session.ip_address }) }}
            </span>

            <span class="tabular-nums">{{
              $t('platform.viewAs.log.started', { date: dateOf(session.started_at) })
            }}</span>

            <span
              v-if="session.ended_at"
              class="tabular-nums"
            >
              {{ $t('platform.viewAs.log.ended', { date: dateOf(session.ended_at) }) }}
            </span>
          </span>
        </div>

        <span
          data-testid="view-as-log-reason"
          :class="[
            'shrink-0 self-start rounded-sm px-2 py-0.5 text-xs font-semibold sm:self-center',
            session.end_reason ? 'bg-panel-muted text-body' : 'bg-warn-100 text-warn-900',
          ]"
        >
          {{ reasonOf(session) }}
        </span>
      </li>
    </ul>

    <OPaginator
      v-if="total > perPage"
      :page
      :perPage
      :total
      @onPage="onPage"
    />
  </TheLayout>
</template>
