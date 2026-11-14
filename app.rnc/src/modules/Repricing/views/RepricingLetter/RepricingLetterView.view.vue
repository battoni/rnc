<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import type { RepricingLetter } from '../../interfaces';
import { formatReal, getI18nRouteName, toRouteId } from '@Helpers';
import { REPRICING_ROUTE_NAMES } from '../../constants';
import { GetRepricingLetterService } from '../../services';

/*
 * The letter of repricing (rnc-1 R26, the demo's `rpLetter`): one client's branded, printable
 * letter — the firm's mark, the date, the client and its CNPJ, the Reforma's new lines of work for
 * it, today's fee and the suggested one from 01/01/2027, signed by the firm with its contact. A page
 * of its own, outside the back office's layout, so printing it prints the letter alone: the toolbar
 * is `print:hidden` and the sheet drops its shadow and its margins on paper.
 */
const HTTP_NOT_FOUND = 404;

const route = useRoute();
const { locale } = useI18n();

const letter = ref<RepricingLetter | null>(null);
const loadFailed = ref(false);
const loading = ref(true);
const notFound = ref(false);

const backRoute = computed(() => ({ name: getI18nRouteName(REPRICING_ROUTE_NAMES.list) }));
const contactLine = computed(() =>
  [letter.value?.firm.address, letter.value?.firm.phone, letter.value?.firm.mailbox].filter(Boolean).join(' · ')
);

onMounted(load);

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;
  notFound.value = false;

  GetRepricingLetterService(toRouteId(route.params['id']))
    .then((response) => (letter.value = response.data))
    .catch(onFailed)
    .finally(() => (loading.value = false));
}

function formatCnpj(cnpj: string): string {
  return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

function formatLongDate(day: string): string {
  return new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'long', year: 'numeric' }).format(
    new Date(`${day}T12:00:00`)
  );
}

function formatShortDate(day: string): string {
  return new Intl.DateTimeFormat(locale.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
    new Date(`${day}T12:00:00`)
  );
}

// EVENTS
function onFailed(failure: { response?: { status?: number } }) {
  notFound.value = failure.response?.status === HTTP_NOT_FOUND;
  loadFailed.value = !notFound.value;
}

function onPrint() {
  window.print();
}
</script>

<template>
  <div
    class="bg-canvas text-body flex min-h-dvh w-full flex-col items-center gap-6 px-4 py-6 sm:px-6 sm:py-10 print:block print:p-0"
  >
    <nav class="flex w-full max-w-3xl flex-wrap items-center justify-between gap-3 print:hidden">
      <RouterLink
        class="touch-target text-heading hover:text-primary-700 inline-flex items-center gap-2 text-sm font-semibold"
        :to="backRoute"
      >
        <i
          aria-hidden="true"
          class="pi pi-arrow-left text-xs"
        />

        {{ $t('repricing.letter.back') }}
      </RouterLink>

      <Button
        v-if="letter"
        data-testid="letter-print"
        icon="pi pi-print"
        :label="$t('repricing.letter.print')"
        @click="onPrint"
      />
    </nav>

    <div
      v-if="loading"
      class="flex w-full max-w-3xl flex-col gap-3"
    >
      <Skeleton height="4rem" />

      <Skeleton height="28rem" />
    </div>

    <div
      v-else-if="notFound || loadFailed"
      class="bg-panel shadow-lift flex w-full max-w-3xl flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ notFound ? $t('repricing.letter.notFound') : $t('repricing.letter.loadFailed') }}</p>

      <Button
        v-if="loadFailed"
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('repricing.retry')"
        @click="load"
      />
    </div>

    <article
      v-else-if="letter"
      class="bg-panel shadow-lift flex w-full max-w-3xl flex-col gap-8 rounded-lg p-6 sm:p-12 print:max-w-none print:rounded-none print:p-0 print:shadow-none"
      data-testid="repricing-letter"
      :data-client="letter.client.id"
    >
      <header
        class="border-primary-500 flex flex-col gap-3 border-b-2 pb-5 sm:flex-row sm:items-end sm:justify-between"
      >
        <MFirmLogo />

        <span class="text-muted text-sm">{{ formatLongDate(letter.issued_on) }}</span>
      </header>

      <div class="flex flex-col gap-1">
        <p class="text-heading text-lg font-semibold break-words">
          {{ $t('repricing.letter.greeting', { name: letter.client.name }) }}
        </p>

        <p class="text-muted text-sm tabular-nums">
          {{ $t('repricing.letter.cnpj', { cnpj: formatCnpj(letter.client.cnpj) }) }}
        </p>
      </div>

      <h1 class="text-heading text-xl font-semibold">
        {{ $t('repricing.letter.subject', { date: formatShortDate(letter.effective_from) }) }}
      </h1>

      <div class="text-body flex flex-col gap-4 leading-relaxed">
        <p v-if="letter.items.length > 0">
          {{
            $t(
              'repricing.letter.intro',
              { count: letter.items.length, firm: letter.firm.name, name: letter.client.name },
              letter.items.length
            )
          }}
        </p>

        <p v-else>{{ $t('repricing.letter.noItems') }}</p>

        <ul
          v-if="letter.items.length > 0"
          class="flex list-disc flex-col gap-1.5 pl-6"
        >
          <li
            v-for="item in letter.items"
            data-testid="letter-item"
            :key="item.title"
          >
            {{ item.title }}
          </li>
        </ul>
      </div>

      <div class="bg-panel-muted print:border-line grid grid-cols-1 gap-4 rounded-md p-5 sm:grid-cols-2 print:border">
        <div class="flex flex-col gap-1">
          <span class="text-muted text-xs font-semibold tracking-wide uppercase">{{
            $t('repricing.letter.feeToday')
          }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="letter-current"
          >
            {{ formatReal(letter.current_cents) }}
          </strong>
        </div>

        <div class="flex flex-col gap-1">
          <span class="text-muted text-xs font-semibold tracking-wide uppercase">
            {{ $t('repricing.letter.feeFrom', { date: formatShortDate(letter.effective_from) }) }}
          </span>

          <strong
            class="text-primary-700 text-2xl font-semibold tabular-nums"
            data-testid="letter-suggested"
          >
            {{ formatReal(letter.suggested_cents) }}
            <span class="text-muted text-base font-medium">(+{{ letter.pct }}%)</span>
          </strong>
        </div>
      </div>

      <div class="text-body flex flex-col gap-4 leading-relaxed">
        <p>
          {{
            $t('repricing.letter.fee', {
              current: formatReal(letter.current_cents),
              date: formatShortDate(letter.effective_from),
              pct: letter.pct,
              suggested: formatReal(letter.suggested_cents),
            })
          }}
        </p>

        <p>{{ $t('repricing.letter.closing') }}</p>

        <p data-testid="letter-signature">
          {{ $t('repricing.letter.regards') }}
          <br />
          <strong class="text-heading font-semibold">{{ letter.firm.name }}</strong>
        </p>
      </div>

      <footer class="border-line text-muted border-t pt-4 text-xs">{{ letter.firm.name }} · {{ contactLine }}</footer>
    </article>
  </div>
</template>
