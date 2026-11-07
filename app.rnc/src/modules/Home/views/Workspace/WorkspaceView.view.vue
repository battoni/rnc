<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import type { WorkspaceKey } from '../../types';
import { WORKSPACES } from '../../constants';

const route = useRoute();

const workspace = computed(() => WORKSPACES.find((item) => item.key === (route.meta.workspace as WorkspaceKey)));
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader :title="`home.workspaces.${workspace?.key}.title`" />
    </template>

    <AEmptyState
      :description="$t(`home.workspaces.${workspace?.key}.upcoming`)"
      :icon="workspace?.icon"
      :title="$t('home.emptyTitle')"
    />
  </TheLayout>
</template>
