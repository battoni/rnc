<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type { Tip } from '../../interfaces';
import type { TipAnchor } from '@Interfaces';
import { useViewport } from '@Composables';
import { TIP_ANCHORS } from '@Constants';
import { HTTP_STATUS } from '@Enums';
import { getI18nRouteName, translateError, withExternalLinks } from '@Helpers';
import { useTipStore } from '@Stores';
import { useUserStore } from '@UserModule';
import { ArchiveTipService, GetTipsService, UnarchiveTipService } from '../../services';

interface RowActionError {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
}

const tipStore = useTipStore();
const userStore = useUserStore();

const { isMobile } = useViewport();
const { resolve } = useRouter();
const { t } = useI18n();
const toast = useToast();

const archiveReason = ref('');
const archiveVisible = ref(false);
const archiving = ref(false);
const archivingTip = ref<Tip | null>(null);
const dialogVisible = ref(false);
const editingTip = ref<Tip | null>(null);
const historyTip = ref<Tip | null>(null);
const historyVisible = ref(false);
const loading = ref(false);
const search = ref('');
const showArchived = ref(false);
const tips = ref<Tip[]>([]);

const activeTips = computed(() => tips.value.filter((tip) => !tip.archived_at));
const canArchive = computed(() => archiveReason.value.trim().length > 0);
const canManage = computed(() => userStore.hasPermission('tips.manage'));
const canRead = computed(() => userStore.hasPermission('tips.read'));
const takenAnchorKeys = computed(() => activeTips.value.map((tip) => tip.anchor_key));
const hasFreeAnchor = computed(() => TIP_ANCHORS.some((anchor) => !takenAnchorKeys.value.includes(anchor.key)));

const archiveSubmitProps = computed(() => ({
  disabled: !canArchive.value,
  label: 'tips.archive',
  loading: archiving.value,
  severity: 'danger' as const,
}));
const filteredTips = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) return tips.value;

  return tips.value.filter((tip) => {
    const anchor = TIP_ANCHORS.find((candidate) => candidate.key === tip.anchor_key);
    const haystack = [
      tip.anchor_key,
      anchor ? t(anchor.labelKey) : '',
      anchor ? t(anchor.sectionKey) : '',
      toPreview(tip.content),
    ]
      .join(' ')
      .toLowerCase();

    return haystack.includes(term);
  });
});
/*
 * An anchor that no longer exists in the code is worth showing, not hiding: it means a screen was
 * renamed and this tip stopped rendering anywhere. Falling back to the raw key makes that visible
 * instead of leaving a blank row.
 */
const listItems = computed(() =>
  filteredTips.value.map((tip) => {
    const anchor = TIP_ANCHORS.find((candidate) => candidate.key === tip.anchor_key);

    return {
      ...tip,
      anchorLabel: anchor ? t(anchor.labelKey) : tip.anchor_key,
      isArchived: !!tip.archived_at,
      isOrphan: !anchor,
      lastChangedBy: tip.updated_by ? t('tips.lastChangedBy', { name: tip.updated_by.name }) : null,
      preview: toPreview(tip.content),
      routePath: buildRoutePath(anchor),
      sectionLabel: anchor ? t(anchor.sectionKey) : t('tips.unknownAnchor'),
      statusLabel: tip.archived_at ? t('tips.status.archived') : t('tips.status.active'),
      // `danger`, as an inactive user and an archived showcase row: OListDivided paints no `secondary`.
      statusTone: tip.archived_at ? 'danger' : 'success',
    };
  })
);

onMounted(onTipsViewMounted);

// HELPERS
/** The "see in app" destination, on the right tab, or null when it cannot be built. */
function buildRoutePath(anchor: TipAnchor | undefined): string | null {
  if (!anchor?.routeName) return null;

  /*
   * A destination is a route name plus a query, and nothing else. The origin project also had
   * anchors bound to a specific entity — a tip that only exists on one product category's screen —
   * and resolved the id by loading the catalogue here. That is a product's concern: the bootstrap
   * models the anchor, not the things a project might anchor to. A project that needs it resolves
   * the params in its own local catalogue.
   */
  return resolve({
    name: getI18nRouteName(anchor.routeName),
    query: anchor.routeQuery ?? {},
  }).fullPath;
}

function loadTips() {
  loading.value = true;

  GetTipsService(showArchived.value)
    .then(({ data }) => (tips.value = data ?? []))
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      })
    )
    .finally(() => (loading.value = false));
}

/**
 * `OListDivided` emits rows as plain records, so handlers look the real tip up by id rather than
 * casting a loosely typed object into `Tip` and hoping the shape holds.
 */
function resolveTip(item: Record<string, unknown>): Tip | undefined {
  return tips.value.find((tip) => tip.id === item.id);
}

function replaceTip(saved: Tip) {
  const index = tips.value.findIndex((tip) => tip.id === saved.id);

  if (index < 0) {
    tips.value = [saved, ...tips.value];
    return;
  }

  tips.value = tips.value.map((tip) => (tip.id === saved.id ? saved : tip));
}

/**
 * A 422 names the rule a row action broke (an anchor already taken): a warning that says which.
 * Anything else is the red toast.
 */
function reportRowActionError(error: RowActionError) {
  const refusalKey = Object.values(error?.response?.data?.errors ?? {})[0]?.[0];
  const isRefusal = error?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY && !!refusalKey;

  if (isRefusal) {
    toast.add({ severity: 'warn', summary: translateError(refusalKey!), life: 10000 });
    return;
  }

  toast.add({
    severity: 'error',
    summary: t('errors.somethingWentWrong'),
    detail: t('errors.refreshAndContactAdmin'),
    life: 10000,
  });
}

/** Plain-text summary of a tip for the row and the search index — the row is not the place for markup. */
function toPreview(content: string): string {
  return content
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

// EVENTS
function onArchiveAccept() {
  const tip = archivingTip.value;

  if (!tip || !canArchive.value) return;

  archiving.value = true;

  ArchiveTipService(tip.id, archiveReason.value.trim())
    .then(({ data }) => {
      // Archived rows leave the list unless the filter is asking for them.
      if (!showArchived.value) {
        tips.value = tips.value.filter((candidate) => candidate.id !== data.id);
      }

      if (showArchived.value) replaceTip(data);

      tipStore.applyTip(data);

      toast.add({
        severity: 'success',
        summary: t('tips.archivedSuccess'),
        life: 5000,
      });
    })
    .catch(reportRowActionError)
    .finally(() => {
      archiving.value = false;

      onArchiveClose();
    });
}

function onArchiveClose() {
  archiveReason.value = '';
  archiveVisible.value = false;
  archivingTip.value = null;
}

function onArchiveRequest(item: Record<string, unknown>) {
  const tip = resolveTip(item);

  if (!tip) return;

  archiveReason.value = '';
  archivingTip.value = tip;
  archiveVisible.value = true;
}

function onCreateRequest() {
  editingTip.value = null;
  dialogVisible.value = true;
}

function onDialogClose() {
  dialogVisible.value = false;
  editingTip.value = null;
}

function onDialogSuccess(tip: Tip) {
  onDialogClose();

  replaceTip(tip);

  tipStore.applyTip(tip);
}

function onEditRequest(item: Record<string, unknown>) {
  const tip = resolveTip(item);

  if (!tip) return;

  editingTip.value = tip;
  dialogVisible.value = true;
}

function onHistoryRequest(item: Record<string, unknown>) {
  const tip = resolveTip(item);

  if (!tip) return;

  historyTip.value = tip;
  historyVisible.value = true;
}

function onHistoryRestored(tip: Tip) {
  historyTip.value = tip;

  replaceTip(tip);

  tipStore.applyTip(tip);
}

function onShowArchivedChange() {
  loadTips();
}

function onTipsViewMounted() {
  if (!canRead.value) return;

  loadTips();
}

function onUnarchiveRequest(item: Record<string, unknown>) {
  const tip = resolveTip(item);

  if (!tip) return;

  UnarchiveTipService(tip.id)
    .then(({ data }) => {
      replaceTip(data);

      tipStore.applyTip(data);

      toast.add({
        severity: 'success',
        summary: t('tips.unarchivedSuccess'),
        life: 5000,
      });
    })
    .catch(reportRowActionError);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-info-circle"
        title="tips.title"
      >
        <template
          v-if="!dialogVisible"
          #primaryAction
        >
          <APageHeaderAction
            v-if="canManage"
            data-testid="add-tip"
            icon="pi pi-plus"
            :disabled="!hasFreeAnchor"
            :label="$t('tips.add')"
            @click="onCreateRequest"
          />
        </template>
      </ThePageHeader>
    </template>

    <div class="flex flex-row flex-wrap items-center justify-start gap-4">
      <InputGroup class="sm:w-fit">
        <InputGroupAddon class="border-none shadow">
          <i class="pi pi-search" />
        </InputGroupAddon>

        <InputText
          v-model="search"
          id="tips-search"
          class="min-w-48 border-none shadow"
          :placeholder="$t('common.search')"
        />

        <label
          class="sr-only"
          for="tips-search"
        >
          {{ $t('common.search') }}
        </label>
      </InputGroup>

      <div class="flex items-center gap-2">
        <ToggleSwitch
          v-model="showArchived"
          inputId="tips-show-archived"
          @update:modelValue="onShowArchivedChange"
        />

        <label
          class="text-muted text-sm"
          for="tips-show-archived"
        >
          {{ $t('tips.showArchived') }}
        </label>
      </div>
    </div>

    <div class="flex flex-col gap-4">
      <div
        v-if="!canRead"
        class="border-warn-200 bg-warn-50 text-warn-800 rounded-lg border p-6"
      >
        {{ $t('tips.noAccess') }}
      </div>

      <div
        v-else-if="loading"
        class="flex flex-col gap-3"
      >
        <Skeleton height="4rem" />

        <Skeleton height="4rem" />

        <Skeleton height="4rem" />
      </div>

      <p
        v-else-if="!listItems.length"
        class="text-muted text-sm"
      >
        {{ $t('tips.empty') }}
      </p>

      <OListDivided
        v-else
        :canEdit="canManage"
        :items="listItems"
        :status="{ valueKey: 'statusLabel', toneKey: 'statusTone' }"
        :subtitle="{ valueKey: 'sectionLabel' }"
        :title="{ valueKey: 'anchorLabel' }"
        @onEdit="onEditRequest"
      >
        <template #details="{ item }">
          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
              <span class="text-muted text-xs uppercase">{{ $t('tips.usedIn') }}</span>

              <span class="text-body text-sm">{{ item.sectionLabel }} › {{ item.anchorLabel }}</span>

              <code class="text-muted text-xs">{{ item.anchor_key }}</code>
            </div>

            <Message
              v-if="item.isOrphan"
              severity="warn"
            >
              {{ $t('tips.orphanWarning') }}
            </Message>

            <!-- Safe: sanitised by the API on write. -->
            <div
              class="border-line-strong bg-panel text-body flex min-w-0 flex-col gap-2 rounded-lg border p-4 text-sm break-words"
              v-html="withExternalLinks(String(item.content ?? ''))"
            />

            <div class="flex flex-wrap items-center justify-between gap-2">
              <p
                v-if="item.lastChangedBy"
                class="text-muted text-xs"
              >
                {{ item.lastChangedBy }}
              </p>

              <RouterLink
                v-if="item.routePath"
                :to="item.routePath"
              >
                <Button
                  icon="pi pi-external-link"
                  severity="secondary"
                  size="small"
                  :label="$t('tips.goToPlace')"
                />
              </RouterLink>
            </div>
          </div>
        </template>

        <template #actions="{ item, variant }">
          <template v-if="variant === 'menu'">
            <Button
              text
              class="w-full justify-start"
              data-testid="tip-history"
              icon="pi pi-history"
              severity="secondary"
              :label="$t('tips.history.title')"
              @click="onHistoryRequest(item)"
            />

            <Button
              v-if="item.isArchived && canManage"
              text
              class="w-full justify-start"
              data-testid="tip-unarchive"
              icon="pi pi-replay"
              severity="secondary"
              :label="$t('tips.unarchive')"
              @click="onUnarchiveRequest(item)"
            />

            <Button
              v-if="!item.isArchived && canManage"
              text
              class="w-full justify-start"
              data-testid="tip-archive"
              icon="pi pi-inbox"
              severity="secondary"
              :label="$t('tips.archive')"
              @click="onArchiveRequest(item)"
            />

            <RouterLink
              v-if="item.routePath"
              data-testid="tip-go-to-place"
              :to="item.routePath"
            >
              <Button
                text
                class="w-full justify-start"
                icon="pi pi-external-link"
                severity="secondary"
                :label="$t('tips.goToPlace')"
              />
            </RouterLink>
          </template>

          <template v-else>
            <Button
              rounded
              text
              data-testid="tip-history"
              icon="pi pi-history"
              severity="secondary"
              :aria-label="$t('tips.history.title')"
              @click="onHistoryRequest(item)"
            />

            <Button
              v-if="item.isArchived && canManage"
              rounded
              text
              data-testid="tip-unarchive"
              icon="pi pi-replay"
              severity="secondary"
              :aria-label="$t('tips.unarchive')"
              @click="onUnarchiveRequest(item)"
            />

            <Button
              v-if="!item.isArchived && canManage"
              rounded
              text
              data-testid="tip-archive"
              icon="pi pi-inbox"
              severity="secondary"
              :aria-label="$t('tips.archive')"
              @click="onArchiveRequest(item)"
            />

            <RouterLink
              v-if="item.routePath"
              data-testid="tip-go-to-place"
              :to="item.routePath"
            >
              <!-- Icon-only where the row is tight, labelled where there is room. The label was
                 added because a lone arrow icon did not say where it went; the icon-only form
                 was added because a labelled button fought the title for width on a phone.
                 Both were right about their own width, so the control answers the viewport
                 instead of picking one and being wrong half the time. `aria-label` is on it
                   either way, so the reading never depends on the breakpoint. In the ⋮ menu
                   (variant menu) the label is always on — see the branch above. -->
              <Button
                text
                icon="pi pi-external-link"
                severity="secondary"
                :aria-label="$t('tips.goToPlace')"
                :label="isMobile ? undefined : $t('tips.goToPlace')"
                :rounded="isMobile"
              />
            </RouterLink>
          </template>
        </template>
      </OListDivided>
    </div>

    <aside class="contents">
      <!-- A dialog rather than a ConfirmPopup: the reason is mandatory, and a popup cannot
           validate a field before its accept button fires. -->
      <MMainDialog
        v-model:visible="archiveVisible"
        title="tips.archiveTitle"
        :submitButtonProps="archiveSubmitProps"
        @onClose="onArchiveClose"
        @onSubmit="onArchiveAccept"
      >
        <div class="mx-auto flex w-full max-w-[500px] flex-col gap-2">
          <p class="text-body text-sm">{{ $t('tips.archiveConfirm') }}</p>

          <label
            class="text-muted mt-2 mb-2 block text-sm"
            for="tip-archive-reason"
          >
            {{ $t('tips.form.reason') }} <span class="text-danger-600">*</span>
          </label>

          <InputText
            v-model="archiveReason"
            fluid
            id="tip-archive-reason"
            data-testid="tip-archive-reason"
            :maxlength="255"
          />
        </div>
      </MMainDialog>

      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        :title="editingTip ? 'tips.editTitle' : 'tips.addTitle'"
        @onClose="onDialogClose"
      >
        <MAddEditTipForm
          :key="editingTip?.id ?? 'new'"
          :takenAnchorKeys
          :tip="editingTip"
          @onClose="onDialogClose"
          @onSuccess="onDialogSuccess"
        />
      </MMainDialog>

      <MTipHistoryDialog
        v-model:visible="historyVisible"
        :canManage
        :tip="historyTip"
        @onRestored="onHistoryRestored"
      />
    </aside>
  </TheLayout>
</template>
