<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { SUPPORT_NAMES } from '@Constants';
import { GetSupportNamesService } from '@AuthModule';

const isLoading = ref(true);
const supportNames = ref<string[]>([]);

onBeforeMount(onBeforeMountEvent);

// EVENTS
function onBeforeMountEvent() {
  GetSupportNamesService()
    .then(({ data }) => (supportNames.value = data?.length ? data : [...SUPPORT_NAMES]))
    .catch(() => (supportNames.value = [...SUPPORT_NAMES]))
    .finally(() => (isLoading.value = false));
}
</script>

<template>
  <div class="flex flex-col gap-4 text-center">
    <p class="text-body text-base">
      {{ $t('auth.flow.support.contactAdmins') }}
    </p>

    <ul
      v-if="!isLoading"
      class="flex flex-col gap-2"
    >
      <li
        v-for="name in supportNames"
        :key="name"
      >
        <span class="text-body">{{ name }}</span>
      </li>
    </ul>

    <ul
      v-else
      class="flex flex-col items-center gap-2"
    >
      <li
        v-for="skeletonIndex in 3"
        class="flex w-full justify-center"
        :key="skeletonIndex"
      >
        <Skeleton
          height="1.5rem"
          width="12rem"
        />
      </li>
    </ul>
  </div>
</template>
