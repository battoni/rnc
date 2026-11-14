<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { getI18nRouteName } from '@Helpers';
import { PLATFORM_PERMISSIONS, PLATFORM_ROUTE_NAMES } from '@PlatformModule';
import { PORTAL_ROUTE_NAMES, usePortalLanding } from '@PortalModule';
import { useUserStore } from '@UserModule';
import { useWorkspaces } from '../../composables';

/*
 * The back office's landing (rnc-1). A partner's login (rnc-2 R3) is sent on to its portal at once
 * and sees nothing of this: the router falls every refused back-office route back to Home, so
 * this one redirect is what bounces a partner from all of them. The firm-less platform operator
 * (rnc-4 R24) is sent on to the platform console the same way — its back office is the console —
 * except inside a view-as (R26), where its back office is the viewed firm's.
 */
const router = useRouter();
const userStore = useUserStore();

const { isPortalOnly } = usePortalLanding();
const { workspaces } = useWorkspaces();

const firstName = computed(() => userStore.user?.name ?? '');
const isPlatformOperator = computed(
  () =>
    !!userStore.platform?.is_operator &&
    !userStore.firm &&
    !userStore.platform.view_as &&
    userStore.hasPermission(PLATFORM_PERMISSIONS.firms)
);
const isElsewhere = computed(() => isPortalOnly.value || isPlatformOperator.value);

if (isPortalOnly.value) router.replace({ name: getI18nRouteName(PORTAL_ROUTE_NAMES.home) });

if (isPlatformOperator.value) router.replace({ name: getI18nRouteName(PLATFORM_ROUTE_NAMES.firms) });
</script>

<template>
  <TheLayout v-if="!isElsewhere">
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
