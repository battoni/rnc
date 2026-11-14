<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { FirmNotFoundView } from '@/views';
import { currentFirmSlug, isReachableSignedOut } from '@Helpers';
import { usePublicFirm } from '@SiteModule';
import { GetMeService, useUserStore } from '@UserModule';
import useValidationCases from './useValidationCases';

const userStore = useUserStore();
const { isAuthenticated } = storeToRefs(userStore);

const ROUTE = useRoute();
const { isFirmMissing, load: loadFirm } = usePublicFirm();
const {
  NAVIGATION_MODES,
  isRedirecting,
  navigationMode,
  onSessionProbeFailed,
  publicRouteCase,
  targetRoute,
  unauthenticatedRouteCase,
} = useValidationCases();

const canRender = ref(false);

onBeforeMount(onHardNavigation);
onBeforeMount(onFirmHostCheck);

// HELPERS
function routeMiddleware(): boolean {
  return unauthenticatedRouteCase() && publicRouteCase();
}

function onSessionResolved() {
  targetRoute.value = ROUTE;

  const canProceed = routeMiddleware();

  if (canProceed) canRender.value = true;
}

// EVENTS
/*
 * rnc-4 R3: on a firm host the firm is asked once, at the hard navigation. A 404 `firms.notFound`
 * swaps every path — site, login, back office — for "Escritório não encontrado". The root always
 * has the platform firm, so it never asks here.
 */
function onFirmHostCheck() {
  if (!currentFirmSlug()) return;

  loadFirm();
}

function onHardNavigation() {
  if (isRedirecting.value) {
    if (isAuthenticated.value) onSessionResolved();
    return;
  }

  targetRoute.value = ROUTE;
  navigationMode.value = NAVIGATION_MODES.HARD;

  if (isAuthenticated.value) {
    onSessionResolved();
    return;
  }

  // An open route (the not-found screen) renders like a public one and is never redirected away:
  // `publicRouteCase` only acts on `isPublic`.
  const canStaySignedOut = isReachableSignedOut(ROUTE);

  if (!userStore.hasSessionHint()) {
    if (canStaySignedOut) {
      canRender.value = true;
      return;
    }

    onSessionResolved();
    return;
  }

  if (canStaySignedOut) {
    canRender.value = true;

    GetMeService()
      .then(({ data }) => {
        if (data) {
          userStore.setUserAndPermissions(data, data.permissions ?? []);
          publicRouteCase();
        }
      })
      .catch(onSessionProbeFailed);

    return;
  }

  GetMeService()
    .then(({ data }) => data && userStore.setUserAndPermissions(data, data.permissions ?? []))
    .catch(onSessionProbeFailed)
    .finally(onSessionResolved);
}
</script>

<template>
  <FirmNotFoundView v-if="isFirmMissing" />

  <slot v-else-if="canRender" />
</template>
