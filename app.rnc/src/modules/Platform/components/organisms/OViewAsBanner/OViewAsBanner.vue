<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useFirmBrand } from '@Composables';
import { getI18nRouteName } from '@Helpers';
import { useUserStore } from '@UserModule';
import { PLATFORM_ROUTE_NAMES } from '../../../constants';
import { StopViewAsService } from '../../../services';

/*
 * The view-as banner (rnc-4 R26, A43): while `me.platform.view_as` is set, every back-office screen
 * says which firm the operator is inside — "Você está acessando <firma> como operador da plataforma" —
 * with "Sair". Mounted once by `TheLocalOverlays` (TheLayout's project-owned slot) and pulled to the top
 * of the content column (`order-first`), in flow: it never scrolls away and never covers a row.
 * "Sair" closes the view-as on the API (null when it was already closed — W85), reads the session
 * again, repaints the root's own brand and returns to the console. A failed "Sair" keeps the banner
 * and says so.
 */
const userStore = useUserStore();

const toast = useToast();
const { reload } = useFirmBrand();
const router = useRouter();
const { t } = useI18n();

const isLeaving = ref(false);

const firmName = computed(() => userStore.platform?.view_as?.name ?? '');

// EVENTS
function onLeave() {
  isLeaving.value = true;

  StopViewAsService()
    .then(onLeft)
    .catch(onLeaveFailed)
    .finally(() => (isLeaving.value = false));
}

/*
 * The view-as is closed on the API: the banner goes now, whatever happens next. A failed session or brand read
 * afterwards still lands on the console, whose route guard reads the session again — never a "could not leave"
 * over a view-as that is already over (rnc-4 verify P2-S5).
 */
function onLeft() {
  userStore.endViewAs();

  return userStore
    .fetchMe()
    .then(() => reload())
    .then(goToConsole, goToConsole);
}

function goToConsole() {
  return router.push({ name: getI18nRouteName(PLATFORM_ROUTE_NAMES.firms) });
}

function onLeaveFailed() {
  toast.add({ life: 5000, severity: 'error', summary: t('platform.viewAs.leaveFailed') });
}
</script>

<template>
  <div
    v-if="firmName"
    class="bg-warn-100 text-warn-900 order-first flex flex-wrap items-center justify-between gap-3 px-5 py-2.5 text-sm sm:px-8"
    data-testid="view-as-banner"
    role="status"
  >
    <p class="flex min-w-0 items-center gap-2">
      <i
        aria-hidden="true"
        class="pi pi-eye shrink-0"
      />

      <span class="min-w-0 break-words">{{ $t('platform.viewAs.banner', { firm: firmName }) }}</span>
    </p>

    <Button
      class="shrink-0"
      data-testid="view-as-leave"
      icon="pi pi-sign-out"
      severity="warn"
      size="small"
      :label="$t('platform.viewAs.leave')"
      :loading="isLeaving"
      @click="onLeave"
    />
  </div>
</template>
