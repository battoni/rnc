<script setup lang="ts">
import { onMounted, ref } from 'vue';
import type { PortalMe } from '../../interfaces';
import { GetPortalMeService } from '../../services';

/*
 * The partner's landing (rnc-2 R3): inside Franco's shell, the rail holding only the portal, it
 * names the partner and the firm whose network it is. A placeholder until the inbox (F3) and the
 * statement (F4) arrive. The partner comes from `GET portal/me` — the API decides who it is,
 * never the URL.
 */
const hasFailed = ref(false);
const isLoading = ref(true);
const me = ref<PortalMe | null>(null);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetPortalMeService()
    .then((response) => (me.value = response.data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="portal.title" />
    </template>

    <div
      v-if="isLoading && !me"
      class="flex flex-col gap-3"
    >
      <Skeleton
        height="2rem"
        width="16rem"
      />

      <Skeleton height="4rem" />
    </div>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('portal.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('portal.retry')"
        @click="load"
      />
    </div>

    <section
      v-else-if="me"
      class="flex flex-col gap-2"
      data-testid="portal-home"
    >
      <h2
        class="text-heading text-lg font-semibold break-words"
        data-testid="portal-partner"
      >
        {{ $t('portal.greeting', { name: me.partner.name }) }}
      </h2>

      <p class="text-muted text-sm">
        {{ $t(`partners.specialties.${me.partner.specialty}`) }}
      </p>

      <p class="text-body">
        {{ $t('portal.description', { firm: me.firm.name }) }}
      </p>
    </section>
  </TheLayout>
</template>
