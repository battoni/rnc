<script setup lang="ts"></script>

<template>
  <TheCenteredLayout>
    <!-- Inside the layout, not beside it: a second root node makes this view fragment-rooted, and
         App.vue's `out-in` Transition then never finishes leaving — the next screen never mounts. -->
    <RouterLink
      class="absolute top-4 left-4"
      :to="{ name: 'auth.entrar' }"
    >
      <Button
        link
        class="celer-button-link-secondary"
        icon="pi pi-arrow-left"
        :label="$t('auth.flow.back')"
      />
    </RouterLink>

    <div class="flex w-full max-w-[500px] flex-col items-center justify-center gap-4 px-4">
      <div class="m-auto w-full max-w-[100px]">
        <MFirmLogo variant="min" />
      </div>

      <h1 class="text-heading text-center text-lg font-medium">
        {{ $t('auth.flow.terms.title') }}
      </h1>

      <p class="text-muted text-center text-sm wrap-anywhere">
        {{ $t('auth.flow.terms.description') }}
      </p>

      <p class="text-muted text-center text-sm wrap-anywhere">
        {{ $t('auth.flow.terms.warning') }}
      </p>
    </div>
  </TheCenteredLayout>
</template>
