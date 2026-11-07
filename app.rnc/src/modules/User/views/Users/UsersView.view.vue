<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { User } from '../../interfaces';
import type { Option } from '@Interfaces';
import { usePaginatedList } from '@Composables';
import { HTTP_STATUS } from '@Enums';
import { translateError } from '@Helpers';
import { USER_STATUS } from '../../enums';
import { ArchiveUserService, DeleteUserService, GetUsersService } from '../../services';
import { useUserStore } from '../../store';

interface RequestError {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
}

const userStore = useUserStore();

const confirm = useConfirm();
const { t } = useI18n();
const toast = useToast();
const { items, load, loadFailed, loading, onPage, page, perPage, prependItem, removeItem, total } =
  usePaginatedList<User>(fetchUsers, { onError: onLoadError });

const BADGE_FIELD = { toneKey: 'badgeTone', valueKey: 'badgeLabel' };
// The search lists people by name from ONE bounded request — the API's page ceiling. A product
// with more accounts than that needs a server-side search, not a bigger page.
const SEARCH_OPTIONS_PAGE_SIZE = 100;
const STATUS_FIELD = { toneKey: 'statusTone', valueKey: 'statusLabel' };
const STATUS_ICON_MAP = { danger: 'pi pi-ban', success: 'pi pi-check-circle' };
const SUBTITLE_FIELD = { valueKey: 'username' };
const TITLE_FIELD = { valueKey: 'displayName' };

const searchMultiselectRef = useTemplateRef<{ hide: () => void }>('searchMultiselectRef');

const allUsersForSearch = ref<User[]>([]);
// What the list on screen is filtered by — the ids last SENT, not the ones picked and not yet searched.
const appliedSearch = ref<number[]>([]);
const dialogVisible = ref(false);
const editingUser = ref<User | null>(null);
const orderBy = ref<string>('name_asc');
const search = ref<number[]>([]);

const canArchive = computed(() => userStore.hasPermission('users.archive'));
const canCreate = computed(() => userStore.hasPermission('users.create'));
const canDelete = computed(() => userStore.hasPermission('users.delete'));
const canEdit = computed(() => userStore.hasPermission('users.update'));
const canRead = computed(() => userStore.hasPermission('users.read'));
const currentUserId = computed(() => userStore.user?.id);
const isSearchActive = computed(() => appliedSearch.value.length > 0);

const displayUsers = computed<Record<string, unknown>[]>(() =>
  items.value.map((user) => {
    const isInactive = user.status === USER_STATUS.INACTIVE;

    return {
      ...user,
      badgeLabel: user.role || '—',
      badgeTone: 'contrast',
      displayName: fullName(user),
      isInactive,
      isSelf: user.id === currentUserId.value,
      statusLabel: t(isInactive ? 'common.inactive' : 'common.active'),
      statusTone: isInactive ? 'danger' : 'success',
    };
  })
);
const orderByOptions = computed<Option[]>(() => [
  { label: t('users.sort.nameAsc'), value: 'name_asc' },
  { label: t('users.sort.nameDesc'), value: 'name_desc' },
  { label: t('users.sort.statusAsc'), value: 'status_asc' },
  { label: t('users.sort.statusDesc'), value: 'status_desc' },
  { label: t('users.sort.roleAsc'), value: 'role_asc' },
  { label: t('users.sort.roleDesc'), value: 'role_desc' },
]);
const searchOptions = computed<Option[]>(() => {
  const list = [...allUsersForSearch.value].sort((first, second) =>
    fullName(first).localeCompare(fullName(second), undefined, { sensitivity: 'base' })
  );

  return list.map((userItem) => ({
    label: fullName(userItem),
    value: userItem.id,
  }));
});

watch(orderBy, onOrderByChange);

onMounted(onUsersViewMounted);

// HELPERS
function fullName(user: User): string {
  return `${user.name} ${user.last_name}`.trim();
}

function resolveUser(item: Record<string, unknown>): User | undefined {
  return items.value.find((currentUser) => currentUser.id === item.id);
}

// EVENTS
function fetchSearchOptions() {
  GetUsersService({ order_by: 'name_asc', per_page: SEARCH_OPTIONS_PAGE_SIZE })
    .then(({ data }) => (allUsersForSearch.value = data.data ?? []))
    .catch(() => (allUsersForSearch.value = []));
}

function fetchUsers(params: { page: number; per_page: number }) {
  appliedSearch.value = [...search.value];

  return GetUsersService({
    ...params,
    ids: appliedSearch.value.length ? appliedSearch.value : undefined,
    order_by: orderBy.value,
  });
}

function onArchiveUserRequest(event: Event, item: Record<string, unknown>) {
  const user = resolveUser(item);

  if (!user) return;

  const isInactive = user.status === USER_STATUS.INACTIVE;
  const userName = fullName(user);

  confirm.require({
    accept: onArchiveAccept,
    acceptProps: {
      label: isInactive ? t('users.confirmActions.yesActivate') : t('users.confirmActions.yesArchive'),
      class: 'celer-button-warning',
    },
    group: 'archive',
    message: isInactive
      ? t('users.activateConfirmMessage', { name: userName })
      : t('users.archiveConfirmMessage', { name: userName }),
    rejectProps: {
      label: t('common.actions.cancel'),
      class: 'celer-button-secondary',
    },
    target: event.currentTarget as HTMLElement,
  });

  function onArchiveAccept() {
    ArchiveUserService(user!.id)
      .then(({ data }) => {
        if (!data?.id) return;

        items.value = items.value.map((currentUser) => (currentUser.id === data.id ? data : currentUser));

        toast.add({
          severity: 'success',
          summary: t(isInactive ? 'users.userActivatedSuccess' : 'users.userArchivedSuccess', { name: userName }),
          life: 5000,
        });
      })
      .catch(onRequestRefused);
  }
}

function onCreateUserRequest() {
  editingUser.value = null;
  dialogVisible.value = true;
}

function onDeleteUserRequest(event: Event, item: Record<string, unknown>) {
  const user = resolveUser(item);

  if (!user) return;

  confirm.require({
    accept: onDeleteAccept,
    acceptProps: {
      label: t('users.confirmActions.yesDelete'),
      class: 'celer-button-danger',
    },
    group: 'delete',
    message: t('users.deleteConfirmMessage', { name: fullName(user) }),
    rejectProps: {
      label: t('common.actions.cancel'),
      class: 'celer-button-secondary',
    },
    target: event.currentTarget as HTMLElement,
  });

  function onDeleteAccept() {
    DeleteUserService(user!.id)
      .then(({ data }) => {
        if (!data?.id) return;

        removeItem(data.id);

        toast.add({
          severity: 'success',
          summary: t('users.userDeletedSuccess'),
          life: 5000,
        });
      })
      .catch(onRequestRefused);
  }
}

function onDialogSuccess(user: User) {
  dialogVisible.value = false;
  editingUser.value = null;

  const isExistingRow = items.value.some((currentUser) => currentUser.id === user.id);

  if (isExistingRow) {
    items.value = items.value.map((currentUser) => (currentUser.id === user.id ? user : currentUser));
    return;
  }

  /*
   * A filtered list is not the place to prepend: the new account was not in the search, and a row
   * that does not match the filter above it reads as a broken search. Page 1 is asked again, and
   * the server decides whether the new account belongs there.
   */
  if (isSearchActive.value) {
    reloadFirstPage();
    return;
  }

  prependItem(user);
}

function onEditUserRequest(item: Record<string, unknown>) {
  const user = resolveUser(item);

  if (!user) return;

  editingUser.value = user;
  dialogVisible.value = true;
}

function onLoadError() {
  toast.add({
    severity: 'error',
    summary: t('errors.somethingWentWrong'),
    detail: t('errors.refreshAndContactAdmin'),
    life: 10000,
  });
}

function onOrderByChange() {
  if (!canRead.value) return;

  reloadFirstPage();
}

/*
 * A 422 is the API refusing on a rule — deactivating your own account, say — and it names the
 * rule with an i18n key. That is information, not a failure, so it arrives as a warning in the
 * person's language. Only what is not a 422 is the red "something went wrong".
 */
function onRequestRefused(requestError: RequestError) {
  const isRefused = requestError?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;
  const refusalKey = Object.values(requestError?.response?.data?.errors ?? {})[0]?.[0];

  if (isRefused && refusalKey) {
    toast.add({ severity: 'warn', summary: translateError(refusalKey), life: 10000 });
    return;
  }

  onLoadError();
}

function onSearchCancel() {
  searchMultiselectRef.value?.hide();
  search.value = [];

  reloadFirstPage();
}

function onSearchSubmit() {
  searchMultiselectRef.value?.hide();

  if (!canRead.value) return;

  reloadFirstPage();
}

function onUsersViewMounted() {
  if (!canRead.value) return;

  load();
  fetchSearchOptions();
}

function reloadFirstPage() {
  page.value = 1;
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="users.title">
        <template #titleSuffix>
          <ATip tip-key="users.list" />
        </template>

        <template
          v-if="!dialogVisible"
          #primaryAction
        >
          <APageHeaderAction
            v-if="canCreate"
            data-testid="add-user"
            icon="pi pi-plus"
            :label="$t('users.add')"
            @click="onCreateUserRequest"
          />
        </template>
      </ThePageHeader>
    </template>

    <div class="flex flex-row flex-wrap justify-start gap-4">
      <InputGroup class="sm:w-fit">
        <InputGroupAddon class="border-none shadow">
          <i class="pi pi-search" />
        </InputGroupAddon>

        <!-- A FloatLabel, like MOrderBy's Select beside it: one control naming itself while the other
             whispers a placeholder read as two different kinds of control. The placeholder goes —
             a floating label already occupies the empty field. -->
        <FloatLabel variant="on">
          <MultiSelect
            v-model="search"
            filter
            class="min-w-32 border-none shadow"
            inputId="users-search"
            optionLabel="label"
            optionValue="value"
            ref="searchMultiselectRef"
            :emptyFilterMessage="$t('common.multiselect.emptyFilterMessage')"
            :emptyMessage="$t('common.multiselect.emptyMessage')"
            :filterPlaceholder="$t('common.multiselect.filterPlaceholder')"
            :options="searchOptions"
          >
            <template #footer>
              <div class="border-line flex justify-end gap-2 border-t p-2">
                <Button
                  class="flex-1"
                  severity="secondary"
                  size="small"
                  :label="$t('common.actions.cancel')"
                  @click="onSearchCancel"
                />

                <Button
                  class="flex-1"
                  size="small"
                  :label="$t('common.search')"
                  @click="onSearchSubmit"
                />
              </div>
            </template>
          </MultiSelect>

          <label for="users-search">{{ $t('common.search') }}</label>
        </FloatLabel>
      </InputGroup>

      <MOrderBy
        v-model:orderBy="orderBy"
        class="sm:w-fit"
        :options="orderByOptions"
      />
    </div>

    <div>
      <div
        v-if="!canRead"
        class="border-warn-200 bg-warn-50 text-warn-800 rounded-lg border p-6"
      >
        {{ $t('users.noAccess') }}
      </div>

      <div
        v-else-if="loading"
        class="border-line bg-canvas overflow-hidden rounded-lg border"
        data-testid="users-loading"
      >
        <div
          v-for="skeletonIndex in 6"
          class="border-line flex items-center justify-between gap-4 border-b px-4 py-3 last:border-b-0"
          :key="skeletonIndex"
        >
          <div class="flex items-center gap-3">
            <Skeleton
              height="1.5rem"
              width="4rem"
            />

            <Skeleton
              height="1rem"
              width="9rem"
            />
          </div>

          <Skeleton
            height="1.5rem"
            width="4rem"
          />
        </div>
      </div>

      <div
        v-else-if="displayUsers.length"
        data-testid="users-list"
      >
        <OListDivided
          showDetails
          itemKey="id"
          :badge="BADGE_FIELD"
          :canEdit
          :items="displayUsers"
          :status="STATUS_FIELD"
          :statusIconMap="STATUS_ICON_MAP"
          :subtitle="SUBTITLE_FIELD"
          :title="TITLE_FIELD"
          @onEdit="onEditUserRequest"
        >
          <template #details="{ item }">
            <dl class="flex flex-col gap-4">
              <div class="flex flex-col gap-1">
                <dt class="text-muted text-sm">{{ $t('users.detail.role') }}</dt>

                <dd class="text-body">{{ item.badgeLabel }}</dd>
              </div>

              <div class="flex flex-col gap-1">
                <dt class="text-muted text-sm">{{ $t('users.detail.username') }}</dt>

                <dd class="text-body">{{ item.username }}</dd>
              </div>

              <div
                v-if="item.email"
                class="flex flex-col gap-1"
              >
                <dt class="text-muted text-sm">{{ $t('users.detail.email') }}</dt>

                <dd class="text-body flex flex-wrap items-center gap-2">
                  {{ item.email }}

                  <i
                    v-if="item.email_verified"
                    class="pi pi-check-circle text-success-600"
                    :aria-label="$t('users.emailVerified')"
                  />

                  <span
                    v-else
                    class="text-warn-700 flex items-center gap-1 text-sm"
                  >
                    <i class="pi pi-exclamation-circle" />

                    {{ $t('users.emailUnverified') }}
                  </span>
                </dd>
              </div>

              <div
                v-if="item.phone"
                class="flex flex-col gap-1"
              >
                <dt class="text-muted text-sm">{{ $t('users.detail.phone') }}</dt>

                <dd class="text-body">{{ item.phone }}</dd>
              </div>
            </dl>
          </template>

          <!-- Delete comes first and the status toggle LAST. Delete appears only for an inactive
               account, so a toggle placed before it would move under the cursor while its own
               confirmation is open, and the popup would lose its anchor.
               Mount `#actions` only when archive/delete exist: OListDivided treats a present slot
               as "has actions", and a read-only viewer would get an empty ⋮. -->
          <template
            v-if="canArchive || canDelete"
            #actions="{ item, variant }"
          >
            <template v-if="variant === 'menu'">
              <Button
                v-if="canDelete && item.isInactive"
                text
                class="w-full justify-start"
                icon="pi pi-trash"
                severity="danger"
                :label="$t('common.actions.delete')"
                @click="(event: Event) => onDeleteUserRequest(event, item)"
              />

              <Button
                v-if="canArchive && !item.isSelf"
                text
                class="w-full justify-start"
                severity="secondary"
                :icon="item.isInactive ? 'pi pi-folder-plus' : 'pi pi-folder-open'"
                :label="item.isInactive ? $t('common.actions.activate') : $t('common.actions.archive')"
                @click="(event: Event) => onArchiveUserRequest(event, item)"
              />
            </template>

            <template v-else>
              <Button
                v-if="canDelete && item.isInactive"
                rounded
                text
                class="h-7 w-7 shrink-0"
                icon="pi pi-trash"
                severity="danger"
                :aria-label="$t('common.actions.delete')"
                @click="(event: Event) => onDeleteUserRequest(event, item)"
              />

              <Button
                v-if="canArchive && !item.isSelf"
                rounded
                text
                class="h-7 w-7 shrink-0"
                severity="secondary"
                :aria-label="item.isInactive ? $t('common.actions.activate') : $t('common.actions.archive')"
                :icon="item.isInactive ? 'pi pi-folder-plus' : 'pi pi-folder-open'"
                @click="(event: Event) => onArchiveUserRequest(event, item)"
              />
            </template>
          </template>
        </OListDivided>

        <OPaginator
          :page
          :perPage
          :total
          @onPage="onPage"
        />
      </div>

      <!-- A load that failed is not an empty list: "no users yet" would tell the person their data is gone. -->
      <div
        v-else-if="loadFailed"
        class="bg-panel shadow-lift flex flex-col items-center gap-3 rounded-lg p-10 text-center"
        data-testid="users-load-error"
      >
        <i class="pi pi-exclamation-triangle text-warn-500 text-3xl" />

        <p class="text-heading text-base font-semibold">
          {{ $t('users.loadError.title') }}
        </p>

        <Button
          icon="pi pi-refresh"
          severity="secondary"
          :label="$t('users.loadError.retry')"
          @click="load"
        />
      </div>

      <AEmptyState
        v-else
        :description="$t('users.empty.description')"
        :title="$t('users.empty.title')"
      >
        <template
          v-if="canCreate"
          #action
        >
          <Button
            class="celer-button-soft-primary"
            icon="pi pi-plus"
            :label="$t('users.add')"
            @click="onCreateUserRequest"
          />
        </template>
      </AEmptyState>
    </div>

    <aside class="contents">
      <ConfirmPopup
        class="max-w-70 p-2 text-center"
        group="archive"
        pt:footer="justify-between pt-2"
      />

      <ConfirmPopup
        class="max-w-70 p-2 text-center"
        group="delete"
        pt:footer="justify-between pt-2"
      />

      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        :title="editingUser ? 'users.form.editHeader' : 'users.form.createHeader'"
      >
        <MAddEditUserForm
          :key="editingUser?.id ?? 'new'"
          :user="editingUser"
          @onClose="dialogVisible = false"
          @onSuccess="onDialogSuccess"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
