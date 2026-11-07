<script setup lang="ts">
import { computed } from 'vue';

const visibleModel = defineModel<boolean>('visible', { default: false });

const props = withDefaults(
  defineProps<{
    closable?: boolean;
    closeOnEscape?: boolean;
    closeOnMaskClick?: boolean;
    contentClass?: string;
    headerClass?: string;
    modalClass?: string;
    position?: 'center' | 'top' | 'bottom' | 'left' | 'right';
    widthClass?: string;
  }>(),
  {
    closable: true,
    closeOnEscape: true,
    closeOnMaskClick: true,
    contentClass: '',
    headerClass: '',
    modalClass: '',
    position: 'center',
    widthClass: 'w-[40rem] max-w-[95vw]',
  }
);

const drawerClass = computed(() => {
  if (props.position === 'top' || props.position === 'bottom') {
    return [props.modalClass, 'h-auto w-full'];
  }

  if (props.position === 'left' || props.position === 'right') {
    return [props.modalClass, 'h-full w-auto'];
  }

  return [props.modalClass, 'm-auto h-auto w-auto rounded-xl'];
});

const drawerPosition = computed(() => (props.position === 'center' ? 'bottom' : props.position));
const panelClass = computed(() => {
  if (props.position === 'top' || props.position === 'bottom') {
    return ['w-full rounded-none bg-canvas p-6 shadow-lift-lg', props.widthClass];
  }

  if (props.position === 'left' || props.position === 'right') {
    return ['h-full rounded-none bg-canvas p-6 shadow-lift-lg', props.widthClass];
  }

  return [props.widthClass, 'rounded-xl bg-canvas p-6 shadow-lift-lg'];
});
const wrapperClass = computed(() => {
  if (props.position === 'top') return 'flex w-full items-start justify-center';
  if (props.position === 'bottom') return 'flex w-full items-end justify-center';
  if (props.position === 'left') return 'flex h-full w-full items-center justify-start';
  if (props.position === 'right') return 'flex h-full w-full items-center justify-end';

  return 'flex h-full w-full items-center justify-center';
});
</script>

<template>
  <Drawer
    v-model:visible="visibleModel"
    modal
    :class="drawerClass"
    :closeOnEscape
    :dismissable="closeOnMaskClick"
    :position="drawerPosition"
    :showCloseIcon="closable"
  >
    <template #container="{ closeCallback }">
      <div :class="wrapperClass">
        <div :class="panelClass">
          <div
            v-if="$slots.header || closable"
            :class="['mb-4 flex items-start justify-between gap-3', headerClass]"
          >
            <slot
              v-if="$slots.header"
              name="header"
            />

            <Button
              v-if="closable"
              plain
              text
              icon="pi pi-times"
              :aria-label="$t('common.actions.close')"
              @click="closeCallback"
            />
          </div>

          <div :class="contentClass">
            <slot />
          </div>

          <div
            v-if="$slots.footer"
            class="mt-4"
          >
            <slot name="footer" />
          </div>
        </div>
      </div>
    </template>
  </Drawer>
</template>
