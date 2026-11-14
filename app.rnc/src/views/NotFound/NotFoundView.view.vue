<script setup lang="ts">
import { useRouter } from 'vue-router';
import { getI18nRouteName } from '@Helpers';

const { push } = useRouter();

// EVENTS
function onHomeClick() {
  // Named navigation, not a path string: leaving the catch-all via `push('/')` under App.vue's
  // old out-in route-fade (CenteredLayout root vs TheLayout child) left Home blank until reload.
  push({ name: getI18nRouteName('home') });
}
</script>

<template>
  <TheCenteredLayout>
    <!-- A URL nobody can serve used to render nothing at all: no message, no way back, and no way
         to tell a typo from an outage. It answers everyone, signed in or not — see `routes.ts`.

         This comment sits INSIDE the root: a comment beside it makes this component fragment-rooted,
         and App.vue's `out-in` Transition then never finishes leaving — every navigation away from
         this screen left the app blank until a reload. -->
    <section
      class="flex flex-col items-center gap-6 text-center"
      data-testid="not-found"
    >
      <MFirmLogo variant="min" />

      <div class="flex flex-col gap-2">
        <h1 class="text-heading text-2xl font-semibold tracking-tight">
          {{ $t('notFound.title') }}
        </h1>

        <p class="text-muted text-sm leading-snug">
          {{ $t('notFound.description') }}
        </p>
      </div>

      <Button
        icon="pi pi-home"
        severity="primary"
        :label="$t('notFound.backHome')"
        @click="onHomeClick"
      />
    </section>
  </TheCenteredLayout>
</template>
