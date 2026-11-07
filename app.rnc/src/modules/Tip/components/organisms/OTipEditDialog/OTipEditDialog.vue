<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Tip } from '../../../interfaces';
import { useTipStore } from '@Stores';
import { useUserStore } from '@UserModule';
import { GetTipsService } from '../../../services';

/**
 * The single edit dialog behind every `<ATip>` in the app.
 *
 * Mounted once in `TheLayout` rather than once per tip: tips sit on almost every screen, and a
 * dialog instance per tip would mean a dozen idle dialogs — each with its own mask — on a dense
 * screen. The store carries which anchor was asked for; this listens.
 */
const tipStore = useTipStore();
const userStore = useUserStore();

const takenAnchorKeys = ref<string[]>([]);
const tip = ref<Tip | null>(null);

const canManage = computed(() => userStore.hasPermission('tips.manage'));
const isVisible = computed(() => !!tip.value);

watch(() => tipStore.editingAnchorKey, onEditRequest);

// EVENTS
function onClose() {
  tip.value = null;

  tipStore.closeEdit();
}

/*
 * The list is fetched on demand rather than held in the store: editing a tip from the screen it
 * lives on is rare, and the management payload (authors, timestamps, archived rows) has no
 * business being loaded for every user on every session just in case.
 */
function onEditRequest(anchorKey: string | null) {
  if (!anchorKey || !canManage.value) return;

  GetTipsService()
    .then(({ data }) => {
      const match = data.find((candidate) => candidate.anchor_key === anchorKey);

      if (!match) {
        onClose();
        return;
      }

      takenAnchorKeys.value = data.map((candidate) => candidate.anchor_key);
      tip.value = match;
    })
    .catch(() => onClose());
}

function onSuccess(saved: Tip) {
  tipStore.applyTip(saved);

  onClose();
}
</script>

<template>
  <MMainDialog
    isFooterless
    title="tips.editTitle"
    :visible="isVisible"
    @onClose="onClose"
    @update:visible="onClose"
  >
    <MAddEditTipForm
      :key="tip?.id ?? 'new'"
      :takenAnchorKeys
      :tip
      @onClose="onClose"
      @onSuccess="onSuccess"
    />
  </MMainDialog>
</template>
