<script setup lang="ts">
import { ref } from 'vue';

type MWalletCardVariant = 'info' | 'primary' | 'success' | 'warning';

withDefaults(
  defineProps<{
    description: string;
    title: string;
    value: string;
    variant?: MWalletCardVariant;
  }>(),
  {
    variant: 'info',
  }
);

const accentByVariant: Record<MWalletCardVariant, string> = {
  info: 'bg-info-300',
  primary: 'bg-primary-500',
  success: 'bg-success-500',
  warning: 'bg-warn-200',
};

const titleClassByVariant: Record<MWalletCardVariant, string> = {
  info: 'text-info-700',
  primary: 'text-primary-700',
  success: 'text-success-700',
  warning: 'text-warn-700',
};

const isPopoverVisible = ref(false);
const popoverReference = ref();

// HELPERS
function closePopover() {
  if (!isPopoverVisible.value) return;

  popoverReference.value.hide();
  isPopoverVisible.value = false;
}

// EVENTS
function onTogglePopover(event: Event) {
  popoverReference.value.toggle(event);
  isPopoverVisible.value = !isPopoverVisible.value;
}

function onTriggerKeydown(event: KeyboardEvent) {
  const isActivationKey = event.key === 'Enter' || event.key === ' ';
  if (!isActivationKey) return;

  event.preventDefault();
  onTogglePopover(event);
}
</script>

<template>
  <Card
    class="border-line-strong bg-panel relative overflow-hidden rounded-lg border shadow-none md:hidden lg:block"
    pt:body="p-5"
  >
    <template #content>
      <span :class="['absolute top-0 left-0 h-full w-2 rounded-l-lg', accentByVariant[variant]]" />

      <div class="flex flex-col gap-2">
        <h3 :class="['text-2xl font-semibold', titleClassByVariant[variant]]">
          {{ title }}
        </h3>

        <p class="text-muted max-w-[28ch] text-sm">{{ description }}</p>

        <strong class="text-heading text-4xl leading-none font-semibold tracking-tight">{{ value }}</strong>
      </div>
    </template>
  </Card>

  <Card
    class="border-line-strong bg-panel relative hidden h-full cursor-pointer overflow-hidden rounded-lg border shadow-none md:block lg:hidden"
    pt:body="h-full p-5"
    pt:content="h-full"
    role="button"
    tabindex="0"
    :aria-controls="'wallet-card-popover'"
    :aria-expanded="isPopoverVisible"
    @click="onTogglePopover"
    @keydown="onTriggerKeydown"
  >
    <template #content>
      <span :class="['absolute top-0 left-0 h-full w-2 rounded-l-lg', accentByVariant[variant]]" />

      <div class="flex h-full min-h-16 flex-col gap-2">
        <div class="flex items-center gap-2">
          <h3 :class="['text-2xl font-semibold', titleClassByVariant[variant]]">
            {{ title }}
          </h3>

          <i class="pi pi-info-circle text-muted" />
        </div>

        <strong class="text-heading text-2xl leading-none font-semibold tracking-tight">{{ value }}</strong>
      </div>
    </template>
  </Card>

  <Popover
    dismissable
    id="wallet-card-popover"
    ref="popoverReference"
    @hide="closePopover"
  >
    <div
      class="border-line-strong bg-panel relative flex min-w-72 flex-col gap-2 overflow-hidden rounded-lg border p-5"
    >
      <span :class="['absolute top-0 left-0 h-full w-2 rounded-l-lg', accentByVariant[variant]]" />

      <p class="text-muted max-w-[28ch] text-sm">{{ description }}</p>
    </div>
  </Popover>
</template>
