<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PlatformFirm } from '../../interfaces';
import { GetPlatformFirmsService } from '../../services';

/*
 * The platform console over the firms (rnc-4 R25), the operator's — generalised from rnc-3's brands
 * console: one row per firm with its name, its subdomain link (a new tab), its opt-in modules, when it
 * was made and its leads, the platform firm first and marked. "Novo escritório" opens the firm form;
 * the new firm joins the list at once while its password is still on screen in the dialog. Each row
 * opens the look, the modules or the manager's login; every answer replaces its row — no refetch.
 */
type DialogMode = 'look' | 'manager' | 'modules' | 'new';

const DIALOG_TITLES: Record<DialogMode, string> = {
  look: 'platform.form.editHeading',
  manager: 'platform.manager.heading',
  modules: 'platform.modules.heading',
  new: 'platform.form.newHeading',
};

const CREATED_FORMAT: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit', year: 'numeric' };

const { locale, t } = useI18n();

const dialogMode = ref<DialogMode | null>(null);
const dialogVisible = ref(false);
const firms = ref<PlatformFirm[]>([]);
const hasFailed = ref(false);
const isLoading = ref(true);
const selectedFirm = ref<PlatformFirm | null>(null);

const createdFormat = computed(() => new Intl.DateTimeFormat(locale.value, CREATED_FORMAT));
const dialogTitle = computed(() => DIALOG_TITLES[dialogMode.value ?? 'new']);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetPlatformFirmsService()
    .then(({ data }) => (firms.value = data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function hostOf(siteUrl: string): string {
  return new URL(siteUrl).host;
}

function createdOf(firm: PlatformFirm): string {
  return firm.created_at ? createdFormat.value.format(new Date(firm.created_at)) : '';
}

function moduleName(key: string): string {
  return t(`platform.modules.names.${key}`);
}

function swatchStyle(colour: string | null) {
  return { backgroundColor: colour ?? 'transparent' };
}

function openDialog(mode: DialogMode, firm: PlatformFirm | null) {
  selectedFirm.value = firm;
  dialogMode.value = mode;
  dialogVisible.value = true;
}

// EVENTS
function onClose() {
  dialogVisible.value = false;
}

function onSaved(firm: PlatformFirm) {
  const isKnown = firms.value.some((current) => current.id === firm.id);

  if (!isKnown) {
    // A new firm: first in the list, and the dialog stays — its password is on screen.
    firms.value = [firm, ...firms.value];
    return;
  }

  firms.value = firms.value.map((current) => (current.id === firm.id ? firm : current));
  dialogVisible.value = false;
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-building"
        title="platform.firms.title"
      >
        <template
          v-if="!dialogVisible"
          #actions
        >
          <Button
            data-testid="firm-new"
            icon="pi pi-plus"
            :label="$t('platform.firms.new')"
            @click="openDialog('new', null)"
          />
        </template>
      </ThePageHeader>
    </template>

    <p class="text-body text-sm">{{ $t('platform.firms.lede') }}</p>

    <div
      v-if="isLoading && firms.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="6rem" />

      <Skeleton height="6rem" />
    </div>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('platform.firms.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('platform.firms.retry')"
        @click="load"
      />
    </div>

    <ul
      v-else
      class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg"
      data-testid="firms"
    >
      <li
        v-for="firm in firms"
        class="flex flex-col gap-4 p-4 sm:p-5 xl:flex-row xl:items-center xl:justify-between"
        data-testid="firm-row"
        :key="firm.id"
        :data-firm="firm.slug"
      >
        <div class="flex min-w-0 items-start gap-4">
          <span
            aria-hidden="true"
            class="ring-line relative size-11 shrink-0 overflow-hidden rounded-lg ring-1"
            :style="swatchStyle(firm.brand_primary)"
          >
            <span
              class="absolute right-1.5 bottom-1.5 size-3.5 rounded-full"
              :style="swatchStyle(firm.brand_accent)"
            />
          </span>

          <div class="flex min-w-0 flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-heading font-semibold break-words">{{ firm.name }}</span>

              <span
                v-if="firm.is_platform"
                class="bg-primary-50 text-primary-950 rounded-sm px-2 py-0.5 text-xs font-semibold"
                data-testid="firm-platform"
              >
                {{ $t('platform.firms.platform') }}
              </span>
            </div>

            <a
              class="text-heading touch-target inline-flex min-w-0 items-center gap-1 text-sm font-semibold break-all underline-offset-4 hover:underline"
              data-testid="firm-site-link"
              rel="noopener"
              target="_blank"
              :href="firm.site_url"
            >
              {{ hostOf(firm.site_url) }}

              <i
                aria-hidden="true"
                class="pi pi-external-link text-subtle text-xs"
              />
            </a>

            <ul
              class="flex flex-wrap gap-1.5"
              :aria-label="$t('platform.firms.modules')"
            >
              <li
                v-for="key in firm.modules"
                class="bg-panel-muted text-body rounded-xs px-2 py-0.5 text-xs"
                data-testid="firm-module"
                :key
              >
                {{ moduleName(key) }}
              </li>

              <li
                v-if="firm.modules.length === 0"
                class="text-muted text-xs"
              >
                {{ $t('platform.firms.coreOnly') }}
              </li>
            </ul>

            <span class="text-muted flex flex-wrap gap-x-3 gap-y-1 text-xs">
              <span class="tabular-nums">{{ $t('platform.firms.leads', firm.lead_count) }}</span>

              <span
                v-if="firm.created_at"
                class="tabular-nums"
                data-testid="firm-created"
              >
                {{ $t('platform.firms.created', { date: createdOf(firm) }) }}
              </span>
            </span>
          </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-3">
          <Button
            data-testid="firm-edit"
            icon="pi pi-palette"
            severity="secondary"
            size="small"
            :label="$t('platform.firms.edit')"
            @click="openDialog('look', firm)"
          />

          <Button
            data-testid="firm-modules"
            icon="pi pi-th-large"
            severity="secondary"
            size="small"
            :label="$t('platform.firms.modulesAction')"
            @click="openDialog('modules', firm)"
          />

          <Button
            data-testid="firm-manager-login"
            icon="pi pi-key"
            severity="secondary"
            size="small"
            :label="$t('platform.firms.managerAction')"
            @click="openDialog('manager', firm)"
          />
        </div>
      </li>
    </ul>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        :title="dialogTitle"
      >
        <MAddEditFirmForm
          v-if="dialogMode === 'new' || dialogMode === 'look'"
          :key="`${dialogMode}-${selectedFirm?.id ?? 'new'}`"
          :firm="selectedFirm"
          @onClose="onClose"
          @onSuccess="onSaved"
        />

        <MFirmModulesForm
          v-else-if="dialogMode === 'modules' && selectedFirm"
          :key="`modules-${selectedFirm.id}`"
          :firm="selectedFirm"
          @onClose="onClose"
          @onSuccess="onSaved"
        />

        <MFirmManagerForm
          v-else-if="dialogMode === 'manager' && selectedFirm"
          :key="`manager-${selectedFirm.id}`"
          :firm="selectedFirm"
          @onClose="onClose"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
