<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { Partner, PartnerAccess } from '../../interfaces';
import { translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { PARTNER_PERMISSIONS } from '../../constants';
import { GetPartnersService, UpdatePartnerService } from '../../services';

/*
 * The partner registry (rnc-1 R21) for Reinaldo and the operator: the firm's network, one row per
 * partner with its specialty, the address referrals go to and whether it is active. "Novo parceiro"
 * and "Editar" open the form; "Desativar" takes a partner out of routing at once and "Reativar"
 * brings it back — reversible, so no confirm popup. A partner is never deleted: the trail points
 * at it. The list is the whole registry (a firm's network is a few dozen at most), not a page.
 * "Acesso ao portal" (rnc-2 R2) opens an active partner's login — create it, a new password,
 * revoke — and the row then wears "Portal ativo"; a password stays inside that panel.
 */
interface ToggleError {
  response?: { status?: number };
  translatedMessage?: string;
}

const HTTP_UNPROCESSABLE = 422;
const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const { t } = useI18n();
const toast = useToast();

const accessPartner = ref<Partner | null>(null);
const accessVisible = ref(false);
const dialogVisible = ref(false);
const editingPartner = ref<Partner | null>(null);
const hasFailed = ref(false);
const isLoading = ref(true);
const partners = ref<Partner[]>([]);
const togglingId = ref<number | null>(null);

const activeCount = computed(() => partners.value.filter((partner) => partner.active).length);
const canManage = computed(() => userStore.hasPermission(PARTNER_PERMISSIONS.manage));
const dialogTitle = computed(() => (editingPartner.value ? 'partners.form.editTitle' : 'partners.form.newTitle'));

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetPartnersService()
    .then((response) => (partners.value = response.data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function replacePartner(partner: Partner) {
  partners.value = partners.value.map((current) => (current.id === partner.id ? partner : current));
}

function formatPhone(phone: string): string {
  return phone.replace(/^55(\d{2})(\d{4,5})(\d{4})$/, '($1) $2-$3');
}

// EVENTS
function onAccess(partner: Partner) {
  accessPartner.value = partner;
  accessVisible.value = true;
}

function onAccessChanged(access: PartnerAccess) {
  const partner = partners.value.find((current) => current.id === access.partner_id);

  if (partner) replacePartner({ ...partner, access });
}

function onCreate() {
  editingPartner.value = null;
  dialogVisible.value = true;
}

function onEdit(partner: Partner) {
  editingPartner.value = partner;
  dialogVisible.value = true;
}

function onSaved(partner: Partner) {
  const isNew = !partners.value.some((current) => current.id === partner.id);

  dialogVisible.value = false;
  editingPartner.value = null;

  partners.value = isNew
    ? [partner, ...partners.value]
    : partners.value.map((current) => (current.id === partner.id ? partner : current));

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('partners.saved', { name: partner.name }) });
}

function onToggle(partner: Partner) {
  if (togglingId.value !== null) return;

  const payload = { active: !partner.active };

  togglingId.value = partner.id;

  UpdatePartnerService(partner.id, payload)
    .then((response) => onToggled(response.data))
    .catch(onToggleFailed)
    .finally(() => (togglingId.value = null));
}

function onToggled(partner: Partner) {
  replacePartner(partner);

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t(partner.active ? 'partners.reactivated' : 'partners.deactivated', { name: partner.name }),
  });
}

function onToggleFailed(failure: ToggleError) {
  toast.add({
    life: TOAST_LIFE_MS,
    severity: failure.response?.status === HTTP_UNPROCESSABLE ? 'warn' : 'error',
    summary: failure.translatedMessage ?? translateError('errors.unexpected'),
  });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="partners.title">
        <template
          v-if="!dialogVisible"
          #primaryAction
        >
          <APageHeaderAction
            v-if="canManage"
            data-testid="add-partner"
            icon="pi pi-plus"
            :label="$t('partners.add')"
            @click="onCreate"
          />
        </template>
      </ThePageHeader>
    </template>

    <p class="text-body text-sm">
      {{ $t('partners.lede', { active: activeCount, total: partners.length }) }}
    </p>

    <div
      v-if="isLoading && partners.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="5rem" />

      <Skeleton height="5rem" />

      <Skeleton height="5rem" />
    </div>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('partners.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('partners.retry')"
        @click="load"
      />
    </div>

    <AEmptyState
      v-else-if="partners.length === 0"
      icon="pi pi-sitemap"
      :description="$t('partners.emptyText')"
      :title="$t('partners.emptyTitle')"
    />

    <ul
      v-else
      class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg"
      data-testid="partners"
    >
      <li
        v-for="partner in partners"
        class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between"
        data-testid="partner-row"
        :key="partner.id"
        :data-active="String(partner.active)"
      >
        <div :class="['flex min-w-0 flex-col gap-1', { 'opacity-70': !partner.active }]">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-heading font-semibold break-words">{{ partner.name }}</span>

            <span
              data-testid="partner-status"
              :class="[
                'rounded-sm px-2 py-0.5 text-xs font-semibold',
                partner.active ? 'bg-success-50 text-success-800' : 'bg-panel-muted text-muted',
              ]"
            >
              {{ partner.active ? $t('partners.active') : $t('partners.inactive') }}
            </span>

            <span
              v-if="partner.access?.active"
              class="bg-primary-50 text-primary-950 rounded-sm px-2 py-0.5 text-xs font-semibold"
              data-testid="partner-portal"
            >
              {{ $t('partners.access.active') }}
            </span>
          </div>

          <span class="text-body text-sm">{{ $t(`partners.specialties.${partner.specialty}`) }}</span>

          <span class="text-muted flex flex-wrap gap-x-3 gap-y-1 text-xs">
            <span class="break-all">{{ partner.email }}</span>

            <span
              v-if="partner.phone"
              class="tabular-nums"
            >
              {{ formatPhone(partner.phone) }}
            </span>

            <span v-if="partner.city">{{ partner.city }}</span>
          </span>
        </div>

        <div
          v-if="canManage"
          class="flex shrink-0 flex-wrap gap-3"
        >
          <Button
            data-testid="partner-edit"
            icon="pi pi-pencil"
            severity="secondary"
            size="small"
            :label="$t('partners.edit')"
            @click="onEdit(partner)"
          />

          <Button
            v-if="partner.active"
            data-testid="partner-access"
            icon="pi pi-key"
            severity="secondary"
            size="small"
            :label="$t('partners.portalAccess')"
            @click="onAccess(partner)"
          />

          <Button
            data-testid="partner-toggle"
            size="small"
            :icon="partner.active ? 'pi pi-ban' : 'pi pi-replay'"
            :label="partner.active ? $t('partners.deactivate') : $t('partners.reactivate')"
            :loading="togglingId === partner.id"
            :severity="partner.active ? 'warn' : 'secondary'"
            @click="onToggle(partner)"
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
        <MAddEditPartnerForm
          :key="editingPartner?.id ?? 'new'"
          :partner="editingPartner"
          @onClose="dialogVisible = false"
          @onSuccess="onSaved"
        />
      </MMainDialog>

      <MMainDialog
        v-model:visible="accessVisible"
        isFooterless
        title="partners.access.title"
      >
        <MPartnerAccessForm
          v-if="accessPartner"
          :key="accessPartner.id"
          :partner="accessPartner"
          @onChanged="onAccessChanged"
          @onClose="accessVisible = false"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
