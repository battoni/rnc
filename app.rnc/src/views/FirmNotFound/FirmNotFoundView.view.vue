<script setup lang="ts">
import { ROOT_DOMAIN } from '@Constants';
import { rootSiteUrl } from '@Helpers';

const ROOT_URL = rootSiteUrl(window.location, ROOT_DOMAIN);
</script>

<template>
  <TheCenteredLayout>
    <!-- rnc-4 R3: a subdomain the API does not know. Not a route: `TheRouteGuard` shows it in place
         of whatever path was asked, and the way out is a link, because the root is another host. -->
    <section
      class="flex flex-col items-center gap-6 text-center"
      data-testid="firm-not-found"
    >
      <MFirmLogo variant="min" />

      <div class="flex flex-col gap-2">
        <h1 class="text-heading text-2xl font-semibold tracking-tight">
          {{ $t('firmNotFound.title') }}
        </h1>

        <p class="text-muted text-sm leading-snug">
          {{ $t('firmNotFound.description') }}
        </p>
      </div>

      <Button
        as="a"
        icon="pi pi-home"
        severity="primary"
        :href="ROOT_URL"
        :label="$t('firmNotFound.goToRoot')"
      />
    </section>
  </TheCenteredLayout>
</template>
