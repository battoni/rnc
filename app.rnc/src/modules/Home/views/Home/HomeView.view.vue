<script setup lang="ts">
import { computed } from 'vue';
import { getI18nRouteName } from '@Helpers';
import { useUserStore } from '@UserModule';
import { useWorkspaces } from '../../composables';

const userStore = useUserStore();

const { workspaces } = useWorkspaces();

const firstName = computed(() => userStore.user?.name ?? '');
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="home.title" />
    </template>

    <div class="flex flex-col gap-6">
      <header class="flex flex-col gap-2">
        <h2 class="text-heading text-lg font-semibold">
          {{ $t('home.greeting', { name: firstName }) }}
        </h2>

        <p class="text-muted">
          {{ $t('home.description') }}
        </p>
      </header>

      <ul class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <li
          v-for="workspace in workspaces"
          class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-5"
          data-testid="workspace-card"
          :key="workspace.key"
          :data-workspace="workspace.key"
        >
          <span class="flex items-center gap-3">
            <i :class="[workspace.icon, 'text-subtle text-xl']" />

            <span class="text-heading font-semibold">
              {{ $t(`home.workspaces.${workspace.key}.title`) }}
            </span>
          </span>

          <p class="text-muted text-sm">
            {{ $t(`home.workspaces.${workspace.key}.description`) }}
          </p>

          <RouterLink
            class="text-heading mt-auto text-sm font-semibold underline-offset-4 hover:underline"
            :to="{ name: getI18nRouteName(workspace.routeName) }"
          >
            {{ $t('home.open') }}
          </RouterLink>
        </li>
      </ul>
    </div>
  </TheLayout>
</template>
