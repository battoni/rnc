<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { SUPPORT_NAMES } from '@Constants';
import { GetSupportNamesService } from '../../services';

const isLoading = ref(true);
const supportNames = ref<string[]>([]);

onBeforeMount(onGetSupportNamesRequest);

// EVENTS
function onGetSupportNamesRequest() {
  GetSupportNamesService()
    .then(({ data }) => (supportNames.value = data?.length ? data : SUPPORT_NAMES))
    .catch(() => (supportNames.value = SUPPORT_NAMES))
    .finally(() => (isLoading.value = false));
}
</script>

<template>
  <TheCenteredLayout>
    <!-- Inside the layout, not beside it: a second root node makes this view fragment-rooted, and
         App.vue's `out-in` Transition then never finishes leaving — the next screen never mounts. -->
    <RouterLink
      class="absolute top-4 left-4"
      to="/login"
    >
      <Button
        link
        class="celer-button-link-secondary"
        icon="pi pi-arrow-left"
        :label="$t('auth.forgotPassword.back')"
      />
    </RouterLink>

    <div class="flex max-w-[500px] flex-col items-center justify-center gap-4">
      <div class="m-auto w-full max-w-[100px]">
        <ALogo variant="min" />
      </div>

      <div class="flex max-w-[400px] flex-col items-center justify-center gap-2">
        <h1 class="text-heading text-2xl font-bold">{{ $t('auth.forgotPassword.title') }}</h1>

        <p class="text-body text-center text-lg">
          {{ $t('auth.forgotPassword.description') }}
        </p>

        <ul
          v-if="!isLoading"
          class="flex flex-col items-center justify-center gap-2"
        >
          <li
            v-for="supportName in supportNames"
            :key="supportName"
          >
            <span class="text-heading text-semibold text-left text-lg">{{ supportName }}</span>
          </li>
        </ul>

        <ul
          v-else
          class="flex flex-col items-center justify-center gap-2"
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
    </div>
  </TheCenteredLayout>
</template>
