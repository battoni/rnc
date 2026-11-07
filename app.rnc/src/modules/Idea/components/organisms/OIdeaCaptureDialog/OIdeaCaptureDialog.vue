<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import type { IdeaPayload } from '../../../interfaces';
import { TOAST_ERROR_LIFE_MS, TOAST_SUCCESS_LIFE_MS } from '../../../constants';
import { CreateIdeaService } from '../../../services';
import useIdeaStore from '../../../store';

/**
 * "Send an idea", from any screen, with the area already answered by the route the person was on.
 *
 * This is mounted ONCE, above every screen, and that is the entire point: the moment somebody has
 * something to say about the product is the moment the product annoyed them, and a board they have
 * to navigate to is a board they reach after the feeling has passed.
 *
 * Navigating away closes it: the draft does not ride to the next screen. Opening from anywhere
 * stays; carrying an unfinished form across routes does not.
 *
 * Each view mounts its own `TheLayout`, so a route change tears this host down under App.vue's
 * out-in fade. A watch alone dies with the old instance while `isCaptureOpen` stays true — the
 * new dialog opens again. `onBeforeUnmount` clears the store as the host leaves; the watch covers
 * an in-place path change if the host ever survives.
 *
 * It writes straight to the API rather than asking a parent to. There is no parent — it lives in
 * the layout — and the board's store is where the result belongs anyway, so a person who sends an
 * idea from Hoje and then walks to Ideias finds it already there.
 */
const ideaStore = useIdeaStore();

const route = useRoute();
const { t } = useI18n();
const toast = useToast();

const isSaving = ref(false);

const isVisible = computed({
  get: () => ideaStore.isCaptureOpen,
  set: (value: boolean) => (value ? ideaStore.openCapture(ideaStore.captureArea) : ideaStore.closeCapture()),
});

watch(() => route.fullPath, onRouteChange);
onBeforeUnmount(onHostUnmount);

// EVENTS

function onClose() {
  ideaStore.closeCapture();
}

function onHostUnmount() {
  if (!ideaStore.isCaptureOpen) return;

  ideaStore.closeCapture();
}

function onRouteChange() {
  if (!ideaStore.isCaptureOpen) return;

  ideaStore.closeCapture();
}

function onSubmit(payload: IdeaPayload) {
  isSaving.value = true;

  CreateIdeaService(payload)
    .then(({ data }) => {
      ideaStore.applyIdeaSaved(data);
      ideaStore.closeCapture();

      toast.add({
        severity: 'success',
        summary: t('ideas.toast.createdTitle'),
        detail: t('ideas.toast.createdDetail'),
        life: TOAST_SUCCESS_LIFE_MS,
      });
    })
    .catch((error) => {
      toast.add({
        severity: 'error',
        summary: t('ideas.toast.errorTitle'),
        detail: error?.translatedMessage ?? t('ideas.toast.errorDetail'),
        life: TOAST_ERROR_LIFE_MS,
      });
    })
    .finally(() => (isSaving.value = false));
}
</script>

<template>
  <MMainDialog
    v-model:visible="isVisible"
    isFooterless
    title="ideas.capture.title"
    @onClose="onClose"
  >
    <MIdeaForm
      :key="ideaStore.captureArea ?? 'general'"
      :initialArea="ideaStore.captureArea"
      :isSaving
      @onClose="onClose"
      @onSubmit="onSubmit"
    />
  </MMainDialog>
</template>
