<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Specialist, SpecialistHour } from '../../interfaces';
import { getI18nRouteName } from '@Helpers';
import { FIRM_SETTINGS_ROUTE_NAMES } from '../../constants';
import { GetSpecialistsService } from '../../services';

/*
 * The specialists' weekly hours (rnc-4 R32, C17): each bookable specialist with the windows the
 * agenda offers a visitor, and its hours editor. Who is a specialist is the team screen's flag — a
 * person switched off leaves this list and keeps its hours for when it comes back (C17).
 */
const { locale } = useI18n();

const dialogVisible = ref(false);
const hasFailed = ref(false);
const isLoading = ref(true);
const selected = ref<Specialist | null>(null);
const specialists = ref<Specialist[]>([]);

const weekdayFormat = computed(() => new Intl.DateTimeFormat(locale.value, { weekday: 'short' }));

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetSpecialistsService()
    .then(({ data }) => (specialists.value = data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function hourLabel(hour: SpecialistHour): string {
  // 2024-01-01 was a Monday: day n of that week is ISO weekday n.
  const weekday = weekdayFormat.value.format(new Date(2024, 0, hour.weekday));

  return `${weekday} ${hour.opens_at}–${hour.closes_at}`;
}

// EVENTS
function onEdit(specialist: Specialist) {
  selected.value = specialist;
  dialogVisible.value = true;
}

function onClose() {
  dialogVisible.value = false;
}

function onSaved(hours: SpecialistHour[]) {
  const id = selected.value?.id;

  specialists.value = specialists.value.map((specialist) =>
    specialist.id === id ? { ...specialist, hours } : specialist
  );
  dialogVisible.value = false;
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-calendar-clock"
        title="firmSettings.specialists.title"
      />
    </template>

    <p class="text-body text-sm">{{ $t('firmSettings.specialists.lede') }}</p>

    <div
      v-if="isLoading"
      class="flex flex-col gap-3"
    >
      <Skeleton height="5rem" />

      <Skeleton height="5rem" />
    </div>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('firmSettings.specialists.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('firmSettings.retry')"
        @click="load"
      />
    </div>

    <div
      v-else-if="specialists.length === 0"
      class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-8 text-center"
      data-testid="specialists-empty"
    >
      <p class="text-body">{{ $t('firmSettings.specialists.empty') }}</p>

      <RouterLink
        class="text-heading touch-target text-sm font-semibold underline-offset-4 hover:underline"
        :to="{ name: getI18nRouteName(FIRM_SETTINGS_ROUTE_NAMES.team) }"
      >
        {{ $t('firmSettings.specialists.goToTeam') }}
      </RouterLink>
    </div>

    <ul
      v-else
      class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg"
    >
      <li
        v-for="specialist in specialists"
        class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between"
        data-testid="specialist-row"
        :key="specialist.id"
      >
        <div class="flex min-w-0 flex-col gap-2">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-heading font-semibold break-words">{{ specialist.name }}</span>

            <span
              v-if="specialist.area"
              class="bg-panel-muted text-body rounded-xs px-2 py-0.5 text-xs"
            >
              {{ specialist.area }}
            </span>
          </div>

          <ul
            v-if="specialist.hours.length > 0"
            class="flex flex-wrap gap-1.5"
            :aria-label="$t('firmSettings.specialists.hours')"
          >
            <li
              v-for="(hour, index) in specialist.hours"
              class="bg-panel-muted text-body rounded-xs px-2 py-0.5 text-xs capitalize tabular-nums"
              data-testid="specialist-hour"
              :key="index"
            >
              {{ hourLabel(hour) }}
            </li>
          </ul>

          <span
            v-else
            class="text-muted text-xs"
            data-testid="specialist-no-hours"
          >
            {{ $t('firmSettings.specialists.noHours') }}
          </span>
        </div>

        <div class="flex shrink-0">
          <Button
            data-testid="specialist-edit"
            icon="pi pi-clock"
            severity="secondary"
            size="small"
            :label="$t('firmSettings.specialists.edit')"
            @click="onEdit(specialist)"
          />
        </div>
      </li>
    </ul>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        title="firmSettings.specialists.formHeading"
      >
        <MSpecialistHoursForm
          v-if="selected"
          :key="selected.id"
          :specialist="selected"
          @onClose="onClose"
          @onSuccess="onSaved"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
