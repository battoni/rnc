<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { isReachableSignedOut } from '@Helpers';
import { GetMeService, useUserStore } from '@UserModule';
import useValidationCases from './useValidationCases';

const userStore = useUserStore();
const { isAuthenticated } = storeToRefs(userStore);

const ROUTE = useRoute();
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
  <slot v-if="canRender" />
</template>
