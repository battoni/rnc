<script setup lang="ts">
const emit = defineEmits<{
  onAccept: [acceptCallback: () => void];
  onReject: [rejectCallback: () => void];
}>();

const props = withDefaults(
  defineProps<{
    cancelLabel?: string;
    confirmLabel?: string;
    confirmSeverity?: 'danger' | 'secondary' | 'success' | 'info' | 'warn' | 'help' | 'contrast';
    group: string;
    loading?: boolean;
    popupClass?: string;
  }>(),
  {
    cancelLabel: undefined,
    confirmLabel: undefined,
    confirmSeverity: 'danger',
    loading: false,
    popupClass: undefined,
  }
);
</script>

<template>
  <ConfirmPopup
    :class="popupClass"
    :group
  >
    <template #container="{ message, acceptCallback, rejectCallback }">
      <div class="flex flex-col gap-4 rounded p-4">
        <div
          v-if="message?.header || message?.message"
          class="flex flex-col gap-2"
        >
          <span
            v-if="message?.header"
            class="block font-semibold"
          >
            {{ message.header }}
          </span>

          <span
            v-if="message?.message"
            class="block"
          >
            {{ message.message }}
          </span>
        </div>

        <div class="flex justify-between gap-3">
          <Button
            severity="secondary"
            size="small"
            variant="outlined"
            :disabled="props.loading"
            :label="props.cancelLabel ?? $t('common.actions.cancel')"
            @click="emit('onReject', rejectCallback)"
          />

          <Button
            size="small"
            :label="props.confirmLabel ?? $t('common.actions.delete')"
            :loading="props.loading"
            :severity="props.confirmSeverity"
            @click="emit('onAccept', acceptCallback)"
          />
        </div>
      </div>
    </template>
  </ConfirmPopup>
</template>
