<script setup lang="ts">
import { computed } from 'vue';
import type { PermissionGroup } from '@AuthModule';

const props = defineProps<{
  allPanelsExpanded: boolean;
  loading: boolean;
  permissionGroup: PermissionGroup;
}>();

const collapsed = defineModel<boolean>({ default: true });

const allSelected = computed({
  get: () => props.permissionGroup.permissions.every((permission) => permission.value),
  set: (value: boolean) => {
    props.permissionGroup.permissions.forEach((permission) => {
      permission.value = value;
    });
  },
});
</script>

<template>
  <Panel
    v-model:collapsed="collapsed"
    toggleable
    class="bg-panel rounded-lg border-0 p-4 shadow-none"
    pt:header="gap-2"
  >
    <template #header>
      <div class="flex w-full min-w-0 items-center gap-2 sm:gap-4">
        <i
          :class="[
            permissionGroup.icon,
            'bg-primary-100 text-primary-950 shrink-0 rounded-sm p-1.5 text-lg font-semibold sm:p-2 sm:text-xl',
          ]"
        />

        <!-- Wraps rather than truncates: in a three-column grid at 1024px every panel read "pe…", and a
             section whose name you cannot read is a section you cannot find. -->
        <h3 class="min-w-0 text-base font-semibold sm:text-xl">{{ $t(permissionGroup.nameKey) }}</h3>
      </div>
    </template>

    <div class="flex flex-col gap-4 pt-4">
      <div
        v-for="permission in permissionGroup.permissions"
        class="flex items-center justify-start gap-4"
        :key="permission.key"
      >
        <ToggleSwitch
          v-model="permission.value"
          :disabled="loading"
          :inputId="permission.key"
        />

        <label
          class="cursor-pointer text-base font-medium"
          :for="permission.key"
        >
          {{ $t(permission.labelKey) }}
        </label>
      </div>

      <div class="flex w-full justify-end pt-2">
        <ToggleSwitch
          v-model="allSelected"
          v-tooltip.top="{
            content: $t(allSelected ? 'rolesAndPermissions.deactivateAll' : 'rolesAndPermissions.activateAll'),
          }"
          :ariaLabel="allSelected ? $t('rolesAndPermissions.deactivateAll') : $t('rolesAndPermissions.activateAll')"
          :disabled="loading"
          :inputId="`toggle-all-${permissionGroup.id}`"
        />
      </div>
    </div>
  </Panel>
</template>
