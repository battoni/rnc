<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { SaveFailure, TeamRoleOption } from '../../interfaces';
import type { Profile } from '@AuthModule';
import type { User } from '@UserModule';
import { usePaginatedList } from '@Composables';
import { translateError } from '@Helpers';
import { GetRolesService } from '@AuthModule';
import { ArchiveUserService, GetUsersService, useUserStore } from '@UserModule';
import { FIRM_SETTINGS_PERMISSIONS, FIRM_TEAM_ROLE_SLUGS } from '../../constants';

/*
 * The firm's team logins for its manager (rnc-4 R31): the list (`auth/users`, scoped to the firm by
 * the API since F1a — another firm's account never arrives here), a new login, an edit, deactivate /
 * reactivate (the API's archive toggle) and a password reset shown once. The User module's services
 * read and toggle; the role is only ever `equipe` or `gestao` (W76), offered from `auth/roles`.
 */
type DialogMode = 'edit' | 'new' | 'reset';

const DIALOG_TITLES: Record<DialogMode, string> = {
  edit: 'firmSettings.team.editHeading',
  new: 'firmSettings.team.newHeading',
  reset: 'firmSettings.reset.heading',
};

const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const confirm = useConfirm();
const toast = useToast();
const { t } = useI18n();
const { items, load, loadFailed, loading, onPage, page, perPage, prependItem, total } =
  usePaginatedList<User>(GetUsersService);

const dialogMode = ref<DialogMode | null>(null);
const dialogVisible = ref(false);
const roles = ref<TeamRoleOption[]>([]);
const selectedUser = ref<User | null>(null);

const canArchive = computed(() => userStore.hasPermission(FIRM_SETTINGS_PERMISSIONS.archive));
const canCreate = computed(() => userStore.hasPermission(FIRM_SETTINGS_PERMISSIONS.create));
const canResetPassword = computed(() => userStore.hasPermission(FIRM_SETTINGS_PERMISSIONS.resetPassword));
const canUpdate = computed(() => userStore.hasPermission(FIRM_SETTINGS_PERMISSIONS.update));
const dialogTitle = computed(() => DIALOG_TITLES[dialogMode.value ?? 'new']);

onMounted(onMount);

// HELPERS
function loadRoles() {
  GetRolesService()
    .then(({ data }) => (roles.value = teamRolesOf(data)))
    .catch(() => (roles.value = []));
}

function teamRolesOf(all: Profile[]): TeamRoleOption[] {
  return FIRM_TEAM_ROLE_SLUGS.map((slug) => all.find((role) => role.slug === slug))
    .filter((role): role is Profile => !!role)
    .map(({ id, name, slug }) => ({ id, name, slug: slug ?? '' }));
}

function isActive(user: User): boolean {
  return user.status !== 'inactive';
}

function fullName(user: User): string {
  return `${user.name} ${user.last_name}`.trim();
}

function replaceRow(user: User) {
  items.value = items.value.map((current) => (current.id === user.id ? user : current));
}

function openDialog(mode: DialogMode, user: User | null) {
  selectedUser.value = user;
  dialogMode.value = mode;
  dialogVisible.value = true;
}

// EVENTS
function onMount() {
  load();

  const needsRoles = canCreate.value || canUpdate.value;

  if (needsRoles) loadRoles();
}

function onClose() {
  dialogVisible.value = false;
}

function onSaved(user: User) {
  const isKnown = items.value.some((current) => current.id === user.id);

  if (!isKnown) {
    // A new login: first in the list, and the dialog stays — its password is on screen.
    prependItem(user);
    return;
  }

  replaceRow(user);
  dialogVisible.value = false;
}

function onToggle(user: User, event: Event) {
  const isDeactivating = isActive(user);

  confirm.require({
    accept: () => onToggleAccept(user),
    acceptProps: {
      class: isDeactivating ? 'celer-button-warning' : 'celer-button-primary',
      label: t(isDeactivating ? 'firmSettings.team.deactivate' : 'firmSettings.team.reactivate'),
    },
    group: 'team-toggle',
    message: t(isDeactivating ? 'firmSettings.team.deactivateConfirm' : 'firmSettings.team.reactivateConfirm', {
      name: fullName(user),
    }),
    rejectProps: { class: 'celer-button-secondary', label: t('firmSettings.cancel') },
    target: event.currentTarget as HTMLElement,
  });
}

function onToggleAccept(user: User) {
  ArchiveUserService(user.id)
    .then(({ data }) => replaceRow(data))
    .catch(onToggleFailed);
}

function onToggleFailed(failure: SaveFailure) {
  const code = Object.values(failure.response?.data?.errors ?? {})[0]?.[0];

  toast.add({
    life: TOAST_LIFE_MS,
    severity: code ? 'warn' : 'error',
    summary: code ? translateError(code) : (failure.translatedMessage ?? translateError('errors.unexpected')),
  });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-users"
        title="firmSettings.team.title"
      >
        <template
          v-if="!dialogVisible && canCreate"
          #actions
        >
          <Button
            data-testid="team-new"
            icon="pi pi-plus"
            :label="$t('firmSettings.team.new')"
            @click="openDialog('new', null)"
          />
        </template>
      </ThePageHeader>
    </template>

    <p class="text-body text-sm">{{ $t('firmSettings.team.lede') }}</p>

    <div
      v-if="loading && items.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="4.5rem" />

      <Skeleton height="4.5rem" />
    </div>

    <div
      v-else-if="loadFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('firmSettings.team.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('firmSettings.retry')"
        @click="load"
      />
    </div>

    <div
      v-else
      class="flex min-w-0 flex-col gap-4"
    >
      <ul class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg">
        <li
          v-for="member in items"
          class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between"
          data-testid="team-row"
          :key="member.id"
          :data-username="member.username"
        >
          <div class="flex min-w-0 flex-col gap-1.5">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-heading font-semibold break-words">{{ fullName(member) }}</span>

              <span class="bg-panel-muted text-body rounded-xs px-2 py-0.5 text-xs">{{ member.role }}</span>

              <span
                v-if="member.is_specialist"
                class="bg-primary-50 text-primary-950 rounded-xs px-2 py-0.5 text-xs font-semibold"
                data-testid="team-specialist"
              >
                {{ $t('firmSettings.team.specialistBadge') }}
              </span>
            </div>

            <span class="text-muted flex flex-wrap gap-x-3 gap-y-1 text-xs">
              <span class="break-all">{{ member.username }}</span>

              <span
                data-testid="team-status"
                :class="isActive(member) ? 'text-success-700' : 'text-danger-700'"
              >
                {{ $t(isActive(member) ? 'firmSettings.team.active' : 'firmSettings.team.inactive') }}
              </span>
            </span>
          </div>

          <div class="flex shrink-0 flex-wrap gap-3">
            <Button
              v-if="canUpdate"
              data-testid="team-edit"
              icon="pi pi-pencil"
              severity="secondary"
              size="small"
              :label="$t('firmSettings.team.edit')"
              @click="openDialog('edit', member)"
            />

            <Button
              v-if="canResetPassword"
              data-testid="team-reset"
              icon="pi pi-key"
              severity="secondary"
              size="small"
              :label="$t('firmSettings.team.reset')"
              @click="openDialog('reset', member)"
            />

            <Button
              v-if="canArchive"
              data-testid="team-toggle"
              severity="secondary"
              size="small"
              :icon="isActive(member) ? 'pi pi-ban' : 'pi pi-check-circle'"
              :label="$t(isActive(member) ? 'firmSettings.team.deactivate' : 'firmSettings.team.reactivate')"
              @click="onToggle(member, $event)"
            />
          </div>
        </li>
      </ul>

      <OPaginator
        :page
        :perPage
        :total
        @onPage="onPage"
      />
    </div>

    <aside class="contents">
      <ConfirmPopup group="team-toggle" />

      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        :title="dialogTitle"
      >
        <MAddEditTeamMemberForm
          v-if="dialogMode === 'new' || dialogMode === 'edit'"
          :key="`${dialogMode}-${selectedUser?.id ?? 'new'}`"
          :roles
          :user="selectedUser"
          @onClose="onClose"
          @onSuccess="onSaved"
        />

        <MTeamPasswordForm
          v-else-if="dialogMode === 'reset' && selectedUser"
          :key="`reset-${selectedUser.id}`"
          :user="selectedUser"
          @onClose="onClose"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
