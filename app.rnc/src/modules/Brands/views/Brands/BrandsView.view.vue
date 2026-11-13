<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { Brand, MainBrand } from '../../interfaces';
import { translateError } from '@Helpers';
import { BRAND_ERROR_KEYS, brandPreviewUrl } from '../../constants';
import { GetBrandsService, ToggleBrandService } from '../../services';

/*
 * The brands console (rnc-3 R5, D3) for the operator: Franco's own brand first — the firm's look,
 * read-only here — then Express and Prime, each with the leads started under it. "Editar" opens the
 * form; "Ver como" opens the public site under the brand in a new tab, as a preview (`previa=1`, A6),
 * and is disabled "Inativa" for a brand that is off (AM12): the site would only show Franco there.
 * "Desativar" takes a brand off the site at once and "Reativar" brings it back — reversible, so no
 * confirm. A brand is never deleted: its leads keep their chip and their filter.
 */
interface ToggleError {
  response?: { data?: { error?: string }; status?: number };
  translatedMessage?: string;
}

const PREVIEW_TARGET = '_blank';
const PREVIEW_FEATURES = 'noopener';
const TOAST_LIFE_MS = 3500;

const { t } = useI18n();
const toast = useToast();

const brands = ref<Brand[]>([]);
const dialogVisible = ref(false);
const editingBrand = ref<Brand | null>(null);
const hasFailed = ref(false);
const isLoading = ref(true);
const main = ref<MainBrand | null>(null);
const togglingId = ref<number | null>(null);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetBrandsService()
    .then(({ data }) => {
      main.value = data.main;
      brands.value = data.brands;
    })
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function replaceBrand(brand: Brand) {
  brands.value = brands.value.map((current) => (current.id === brand.id ? brand : current));
}

function swatchStyle(colour: string | null) {
  return { backgroundColor: colour ?? 'transparent' };
}

// EVENTS
function onEdit(brand: Brand) {
  editingBrand.value = brand;
  dialogVisible.value = true;
}

function onSaved(brand: Brand) {
  dialogVisible.value = false;
  editingBrand.value = null;
  replaceBrand(brand);

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('brands.saved', { name: brand.short_name }) });
}

function onToggle(brand: Brand) {
  if (togglingId.value !== null) return;

  togglingId.value = brand.id;

  ToggleBrandService(brand.id, !brand.active)
    .then(({ data }) => onToggled(data))
    .catch(onToggleFailed)
    .finally(() => (togglingId.value = null));
}

function onToggled(brand: Brand) {
  replaceBrand(brand);

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t(brand.active ? 'brands.reactivated' : 'brands.deactivated', {
      name: brand.short_name,
      slug: brand.slug,
    }),
  });
}

function onToggleFailed(failure: ToggleError) {
  const code = failure.response?.data?.error;
  const key = code ? BRAND_ERROR_KEYS[code] : undefined;

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'error',
    summary: key ? t(key) : (failure.translatedMessage ?? translateError('errors.unexpected')),
  });
}

function onViewAs(brand: Brand) {
  if (!brand.active) return;

  window.open(brandPreviewUrl(brand.slug), PREVIEW_TARGET, PREVIEW_FEATURES);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="brands.title" />
    </template>

    <p class="text-body text-sm">{{ $t('brands.lede') }}</p>

    <div
      v-if="isLoading && !main"
      class="flex flex-col gap-3"
    >
      <Skeleton height="5rem" />

      <Skeleton height="6rem" />

      <Skeleton height="6rem" />
    </div>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('brands.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('brands.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="main">
      <section
        class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
        data-testid="brand-main"
      >
        <div class="flex min-w-0 items-center gap-4">
          <span
            aria-hidden="true"
            class="ring-line relative size-11 shrink-0 overflow-hidden rounded-lg ring-1"
            :style="swatchStyle(main.brand_primary)"
          >
            <span
              class="absolute right-1.5 bottom-1.5 size-3.5 rounded-full"
              :style="swatchStyle(main.brand_accent)"
            />
          </span>

          <div class="flex min-w-0 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-heading font-semibold break-words">{{ main.name }}</span>

              <span class="bg-primary-50 text-primary-950 rounded-sm px-2 py-0.5 text-xs font-semibold">
                {{ $t('brands.main') }}
              </span>
            </div>

            <span class="text-muted text-xs">{{ $t('brands.mainNote') }}</span>
          </div>
        </div>

        <span class="text-body shrink-0 text-sm font-semibold tabular-nums">
          {{ $t('brands.leads', main.lead_count) }}
        </span>
      </section>

      <AEmptyState
        v-if="brands.length === 0"
        icon="pi pi-palette"
        :description="$t('brands.emptyText')"
        :title="$t('brands.emptyTitle')"
      />

      <ul
        v-else
        class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg"
        data-testid="brands"
      >
        <li
          v-for="brand in brands"
          class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between"
          data-testid="brand-row"
          :key="brand.id"
          :data-active="String(brand.active)"
          :data-brand="brand.slug"
        >
          <div :class="['flex min-w-0 items-start gap-4', { 'opacity-70': !brand.active }]">
            <span
              aria-hidden="true"
              class="ring-line relative size-11 shrink-0 overflow-hidden rounded-lg ring-1"
              :style="swatchStyle(brand.brand_primary)"
            >
              <span
                class="absolute right-1.5 bottom-1.5 size-3.5 rounded-full"
                :style="swatchStyle(brand.brand_accent)"
              />
            </span>

            <div class="flex min-w-0 flex-col gap-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-heading font-semibold break-words">{{ brand.name }}</span>

                <span
                  data-testid="brand-status"
                  :class="[
                    'rounded-sm px-2 py-0.5 text-xs font-semibold',
                    brand.active ? 'bg-success-50 text-success-800' : 'bg-panel-muted text-muted',
                  ]"
                >
                  {{ brand.active ? $t('brands.active') : $t('brands.inactive') }}
                </span>
              </div>

              <span
                v-if="brand.tagline"
                class="text-body text-sm break-words"
              >
                {{ brand.tagline }}
              </span>

              <span class="text-muted flex flex-wrap gap-x-3 gap-y-1 text-xs">
                <span class="font-mono">?marca={{ brand.slug }}</span>

                <span class="tabular-nums">{{ $t('brands.leads', brand.lead_count) }}</span>
              </span>
            </div>
          </div>

          <div class="flex shrink-0 flex-wrap gap-3">
            <Button
              data-testid="brand-edit"
              icon="pi pi-pencil"
              severity="secondary"
              size="small"
              :label="$t('brands.edit')"
              @click="onEdit(brand)"
            />

            <Button
              data-testid="brand-view-as"
              size="small"
              :disabled="!brand.active"
              :icon="brand.active ? 'pi pi-external-link' : 'pi pi-eye-slash'"
              :label="brand.active ? $t('brands.viewAs', { name: brand.short_name }) : $t('brands.inactive')"
              :severity="brand.active ? undefined : 'secondary'"
              @click="onViewAs(brand)"
            />

            <Button
              data-testid="brand-toggle"
              size="small"
              :icon="brand.active ? 'pi pi-ban' : 'pi pi-replay'"
              :label="brand.active ? $t('brands.deactivate') : $t('brands.reactivate')"
              :loading="togglingId === brand.id"
              :severity="brand.active ? 'warn' : 'secondary'"
              @click="onToggle(brand)"
            />
          </div>
        </li>
      </ul>
    </template>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        title="brands.form.heading"
      >
        <MAddEditBrandForm
          v-if="editingBrand"
          :key="editingBrand.id"
          :brand="editingBrand"
          @onClose="dialogVisible = false"
          @onSuccess="onSaved"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
