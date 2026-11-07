<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { PermissionGroup, Profile } from '../../interfaces';
import { HTTP_STATUS } from '@Enums';
import { translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { DeleteRoleService, GetRolesService, UpdateRolePermissionsService } from '../../services';

interface RequestError {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
}

const userStore = useUserStore();
const confirm = useConfirm();
const { t } = useI18n();
const toast = useToast();

// The tab value is the role id; no id is 0, so 0 is "no role selected" (PrimeVue `Tabs` takes no null).
const NO_ACTIVE_ROLE = 0;

const activeTab = ref<number>(NO_ACTIVE_ROLE);
const blockingUserNames = ref<string[]>([]);
const deleteInProgress = ref(false);
const editingProfile = ref<Profile | null>(null);
const isDeleteBlockedVisible = ref(false);
const isSaving = ref(false);
const loading = ref(true);
const pendingDeleteRoleId = ref<number | null>(null);
const panelResetKey = ref(0);
const panelStates = ref<Record<string, boolean>>({});
const permissionGroups = ref<PermissionGroup[]>([]);
const profileDialogVisible = ref(false);
const roles = ref<Profile[]>([]);

const activeRole = computed(() => roles.value.find((role) => role.id === activeTab.value) ?? null);
// Every write on this screen — create, edit, delete, save — is `permissions.update` on the API.
const canUpdate = computed(() => userStore.hasPermission('permissions.update'));
/*
 * Controls are held while EITHER is true — nobody should tick a box against a request already in
 * flight. The skeleton is not: it answers `loading` alone, because it replaces the page, and
 * replacing the page is what a save must never do.
 */
const isBusy = computed(() => loading.value || isSaving.value);
// A reader sees each role's switches as they stand, and cannot flip what nothing could save.
const arePanelsLocked = computed(() => isBusy.value || !canUpdate.value);

const allPanelsExpandedModel = computed({
  get: () => Object.values(panelStates.value).every((collapsed) => !collapsed),
  set: (value: boolean) => {
    (activeRole.value?.permissionGroups ?? []).forEach((group) => {
      panelStates.value[group.id] = !value;
    });
  },
});

onMounted(onLoadRoles);

// HELPERS
function resetPanelStates() {
  if (!activeRole.value?.permissionGroups) return;

  const newStates = { ...panelStates.value };
  activeRole.value.permissionGroups.forEach(({ id }) => (newStates[id] = true));
  panelStates.value = newStates;
  panelResetKey.value += 1;
}

function setPermissionGroups(groups: PermissionGroup[]) {
  permissionGroups.value = groups.map(({ icon, id, nameKey, permissions }) => ({
    icon,
    id,
    nameKey,
    permissions: permissions.map(({ key, labelKey }) => ({ key, labelKey, value: false })),
  }));

  panelStates.value = Object.fromEntries(groups.map(({ id }) => [id, true]));
}

// EVENTS
function onLoadRoles() {
  loading.value = true;

  GetRolesService()
    .then(({ data }) => {
      roles.value = data;

      const firstRole = data[0];
      if (firstRole && !activeTab.value) activeTab.value = firstRole.id;

      if (firstRole?.permissionGroups) setPermissionGroups(firstRole.permissionGroups);
    })
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

/*
 * Saving is not loading, and the difference is the whole defect.
 *
 * `loading` drives the skeleton, so raising it here tore the tabs down, put a skeleton over the
 * screen and rebuilt every panel — for a request that changes nothing on screen. What came back
 * was the page reset: `resetPanelStates()` and the collapse below it then closed every group a
 * moderator had opened to make the edit they had just saved.
 */
function onSavePermissionsRequest() {
  if (!activeRole.value) return;

  isSaving.value = true;

  const permissions: Record<string, boolean> = {};
  activeRole.value.permissionGroups.forEach((group) => {
    group.permissions.forEach(({ key, value }) => (permissions[key] = value));
  });

  UpdateRolePermissionsService(activeRole.value.id, permissions)
    .then(async ({ data }) => {
      const roleIndex = roles.value.findIndex(({ id }) => id === data.id);

      const isEditing = roleIndex !== -1;
      if (isEditing) roles.value[roleIndex] = data;

      await userStore.fetchMe();

      toast.add({
        severity: 'success',
        summary: t('rolesAndPermissions.roleUpdatedSuccess', { name: data.name }),
        life: 5000,
      });
    })
    .catch(onSavePermissionsRefused)
    .finally(() => (isSaving.value = false));
}

/*
 * A 422 is the API refusing on a rule — granting a key the person does not hold, or taking one from
 * the administrator — and it names the rule with an i18n key. That is information, not a failure:
 * it arrives as a warning in the person's language, the way `UsersView` does it. Only what is not a
 * 422 is the red "something went wrong" (celer-07, "Refusals from the API").
 */
function onSavePermissionsRefused(requestError: RequestError) {
  const isRefused = requestError?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;
  const refusalKey = Object.values(requestError?.response?.data?.errors ?? {})[0]?.[0];

  if (isRefused && refusalKey) {
    toast.add({ severity: 'warn', summary: translateError(refusalKey), life: 10000 });
    return;
  }

  toast.add({
    severity: 'error',
    summary: t('errors.somethingWentWrong'),
    detail: t('errors.refreshAndContactAdmin'),
    life: 10000,
  });
}

function onNewProfileRequest() {
  editingProfile.value = null;
  profileDialogVisible.value = true;
}

function onEditProfileRequest() {
  editingProfile.value = activeRole.value;
  profileDialogVisible.value = true;
}

function onNewEditProfile(updatedRole: Profile) {
  profileDialogVisible.value = false;
  editingProfile.value = null;

  const roleIndex = roles.value.findIndex(({ id }) => id === updatedRole.id);

  setPermissionGroups(updatedRole.permissionGroups);
  allPanelsExpandedModel.value = false;

  const isEditing = roleIndex !== -1;
  if (isEditing) {
    roles.value[roleIndex] = updatedRole;
    resetPanelStates();
    return;
  }

  roles.value.push(updatedRole);
  if (!activeTab.value) activeTab.value = updatedRole.id;
  panelResetKey.value += 1;
}

function onDeleteProfileRequest(event: Event) {
  if (!activeRole.value) return;

  const { name, id, users = [] } = activeRole.value;

  const target = event.currentTarget as HTMLElement;

  if (users.length > 0) {
    blockingUserNames.value = users;
    isDeleteBlockedVisible.value = true;
    return;
  }

  pendingDeleteRoleId.value = id;
  confirm.require({
    target,
    group: 'delete',
    header: t('rolesAndPermissions.deleteConfirmHeader'),
    message: t('rolesAndPermissions.deleteConfirmMessage', { name }),
    rejectProps: {
      label: t('common.actions.cancel'),
      severity: 'secondary',
    },
  });
}

function onConfirmDeleteAccept(acceptCallback: () => void) {
  const roleId = pendingDeleteRoleId.value;
  if (!roleId) return;

  deleteInProgress.value = true;

  DeleteRoleService(roleId)
    .then(() => {
      roles.value = roles.value.filter((role) => role.id !== roleId);

      const firstRole = roles.value[0];
      activeTab.value = firstRole?.id ?? NO_ACTIVE_ROLE;
      if (firstRole?.permissionGroups) setPermissionGroups(firstRole.permissionGroups);

      pendingDeleteRoleId.value = null;
      acceptCallback();

      toast.add({
        severity: 'success',
        summary: t('rolesAndPermissions.roleDeletedSuccess'),
        life: 5000,
      });
    })
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      })
    )
    .finally(() => (deleteInProgress.value = false));
}

function onConfirmDeleteReject(rejectCallback: () => void) {
  pendingDeleteRoleId.value = null;
  rejectCallback();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="rolesAndPermissions.title">
        <template #titleSuffix>
          <ATip tip-key="roles.permissions" />
        </template>

        <template
          v-if="canUpdate && !profileDialogVisible"
          #primaryAction
        >
          <APageHeaderAction
            data-testid="add-profile"
            icon="pi pi-plus"
            :label="$t('rolesAndPermissions.newProfile')"
            @click="onNewProfileRequest"
          />
        </template>
      </ThePageHeader>
    </template>

    <MRolesAndPermissionsSkeleton v-if="loading" />

    <Tabs
      v-else
      v-model:value="activeTab"
    >
      <TabList class="border-none bg-transparent shadow-none">
        <Tab
          v-for="role in roles"
          class="relative cursor-pointer text-xl"
          :key="role.id"
          :value="role.id"
        >
          {{ role.name }}
        </Tab>
      </TabList>

      <TabPanels class="bg-transparent">
        <TabPanel
          v-for="role in roles"
          class="bg-transparent"
          :key="role.id"
          :value="role.id"
        >
          <MPermissionToolbar
            v-model="allPanelsExpandedModel"
            :canDeleteProfile="activeRole?.slug !== 'administrator'"
            :canUpdate
            :loading="isBusy"
            :tab="role.id"
            @onDeleteProfileRequest="onDeleteProfileRequest"
            @onEditProfileRequest="onEditProfileRequest"
            @onSavePermissionsRequest="onSavePermissionsRequest"
          />

          <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3">
            <MPermissionPanel
              v-for="group in activeRole?.permissionGroups ?? []"
              v-model:collapsed="panelStates[group.id]"
              :key="`${group.id}-${panelResetKey}`"
              :allPanelsExpanded="allPanelsExpandedModel"
              :loading="arePanelsLocked"
              :permissionGroup="group"
            />
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <aside class="contents">
      <MConfirmPopup
        group="delete"
        popupClass="p-2 max-w-80"
        :loading="deleteInProgress"
        @onAccept="onConfirmDeleteAccept"
        @onReject="onConfirmDeleteReject"
      />

      <!-- A dialog, not a ConfirmPopup: nothing is being confirmed. The delete is refused, and this
           says who still uses the role — a list that can run long, which a popup cannot hold. It
           rode on ConfirmDialog with a fully custom #container, so the confirm service did nothing
           but open a modal. The row's own delete confirmation stays a ConfirmPopup. -->
      <MMainDialog
        v-model:visible="isDeleteBlockedVisible"
        title="rolesAndPermissions.cannotDeleteRole"
      >
        <div class="flex flex-col gap-4">
          <p class="text-body">{{ $t('rolesAndPermissions.usersUsingRole', { count: blockingUserNames.length }) }}</p>

          <ul class="text-muted flex flex-col gap-1">
            <li
              v-for="userName in blockingUserNames"
              :key="userName"
            >
              {{ userName }}
            </li>
          </ul>
        </div>

        <template #footer>
          <div class="flex justify-end py-4">
            <Button
              severity="secondary"
              :label="$t('common.actions.ok')"
              @click="isDeleteBlockedVisible = false"
            />
          </div>
        </template>
      </MMainDialog>

      <MMainDialog
        v-model:visible="profileDialogVisible"
        isFooterless
        :title="
          editingProfile ? 'rolesAndPermissions.form.editProfileHeader' : 'rolesAndPermissions.form.newProfileHeader'
        "
      >
        <MAddEditProfileForm
          :key="editingProfile?.id ?? 'new'"
          :permissionGroups
          :profile="editingProfile"
          @onClose="profileDialogVisible = false"
          @onSuccess="onNewEditProfile"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
