<script setup lang="ts">
const emit = defineEmits<{
  onDeleteProfileRequest: [event: Event];
  onEditProfileRequest: [];
  onSavePermissionsRequest: [];
}>();

const props = withDefaults(
  defineProps<{
    loading: boolean;
    tab: number;
    canDeleteProfile?: boolean;
    canUpdate?: boolean;
  }>(),
  { canDeleteProfile: true, canUpdate: true }
);

const modelValue = defineModel<boolean>({ required: true });
</script>

<template>
  <div class="mt-4 mb-4 flex min-w-0 flex-row gap-3 sm:items-center sm:justify-between">
    <div class="flex min-w-0 shrink-0 items-center gap-1.5 sm:gap-2">
      <label
        class="cursor-pointer truncate text-xs font-medium sm:text-sm"
        :for="`toggle-all-panels-${props.tab}`"
      >
        {{ modelValue ? $t('rolesAndPermissions.collapseAll') : $t('rolesAndPermissions.expandAll') }}
      </label>

      <ToggleSwitch
        v-model="modelValue"
        :ariaLabel="modelValue ? $t('rolesAndPermissions.collapseAll') : $t('rolesAndPermissions.expandAll')"
        :disabled="loading"
        :inputId="`toggle-all-panels-${props.tab}`"
      />
    </div>

    <!-- One filled button, the one this screen exists for: edit and delete step back to text, delete
         keeping `danger` because there the colour IS the meaning. `gap-3`, not `gap-1`: on a touch
         screen each small button's hit area grows 6px on every side, so 4px apart they overlap and
         the later sibling wins — aiming at the pencil deletes the profile. All three write, and the
         API asks `permissions.update` for each: without it they are not offered at all. -->
    <div
      v-if="canUpdate"
      class="flex shrink-0 items-center gap-3"
    >
      <Button
        v-tooltip.top="$t('common.save')"
        iconOnly
        data-testid="save-permissions"
        icon="pi pi-save"
        :loading
        @click="emit('onSavePermissionsRequest')"
      />

      <Button
        v-tooltip.top="$t('common.actions.edit')"
        iconOnly
        text
        icon="pi pi-pencil"
        severity="secondary"
        :loading
        @click="emit('onEditProfileRequest')"
      />

      <Button
        v-tooltip.top="
          canDeleteProfile ? $t('common.actions.delete') : $t('rolesAndPermissions.cannotDeleteAdministratorRole')
        "
        iconOnly
        text
        icon="pi pi-trash"
        severity="danger"
        :disabled="!canDeleteProfile || loading"
        :loading
        @click="(event) => emit('onDeleteProfileRequest', event)"
      />
    </div>
  </div>
</template>
