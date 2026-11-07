<script setup lang="ts">
import { computed, onMounted, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSingleTip } from '@Composables';
import { withExternalLinks } from '@Helpers';
import { useTipStore } from '@Stores';
import { useUserStore } from '@UserModule';

const props = withDefaults(
  defineProps<{
    ariaLabel?: string;
    /** Anchor key declared in `TIP_ANCHORS`. The text itself comes from the database. */
    tipKey: string;
  }>(),
  {
    ariaLabel: undefined,
  }
);

const tipStore = useTipStore();
const userStore = useUserStore();

const { t } = useI18n();
const { closeTip, openTip } = useSingleTip();

const popoverId = useId();
const isOpen = ref(false);
const popoverReference = ref();

const canManage = computed(() => userStore.hasPermission('tips.manage'));
const content = computed(() => tip.value?.content ?? '');
const resolvedAriaLabel = computed(() => props.ariaLabel ?? t('common.tip'));
const tip = computed(() => tipStore.tipFor(props.tipKey));

onMounted(onComponentMount);

// HELPERS
function hide() {
  popoverReference.value?.hide();
}

// EVENTS
function onComponentMount() {
  tipStore.ensureLoaded();
}

function onEditClick() {
  hide();

  tipStore.requestEdit(props.tipKey);
}

function onHide() {
  isOpen.value = false;
  closeTip(hide);
}

function onShow() {
  isOpen.value = true;
  openTip(hide);
}

function onToggle(event: Event) {
  popoverReference.value?.toggle(event);
}

function onTriggerKeydown(event: KeyboardEvent) {
  const isActivationKey = event.key === 'Enter' || event.key === ' ';
  if (!isActivationKey) return;

  event.preventDefault();
  onToggle(event);
}
</script>

<template>
  <span
    v-if="tip"
    class="tutor-tip inline-flex"
  >
    <Button
      rounded
      text
      class="size-8 shrink-0 pointer-coarse:size-11"
      icon="pi pi-info-circle"
      severity="secondary"
      :aria-controls="popoverId"
      :aria-expanded="isOpen"
      :aria-label="resolvedAriaLabel"
      @click="onToggle"
      @keydown="onTriggerKeydown"
    />

    <Popover
      dismissable
      :id="popoverId"
      ref="popoverReference"
      @hide="onHide"
      @show="onShow"
    >
      <div class="border-line-strong bg-panel flex max-w-[32ch] flex-col gap-2 rounded-lg border p-4">
        <!-- `v-html` is safe HERE because the API sanitises tip content on WRITE: what is stored
             is already the allow-listed subset. Never render tip content that did not come
             through `GET /tips/active`. -->
        <div
          class="tip-body text-muted flex min-w-0 flex-col gap-2 text-sm break-words"
          data-testid="tip-body"
          v-html="withExternalLinks(content)"
        />

        <div
          v-if="canManage"
          class="border-line flex justify-end border-t pt-2"
        >
          <Button
            text
            class="text-xs"
            data-testid="tip-edit"
            icon="pi pi-pencil"
            severity="secondary"
            size="small"
            :label="$t('tips.edit')"
            @click="onEditClick"
          />
        </div>
      </div>
    </Popover>
  </span>
</template>

<style scoped>
@reference "../../../styles/main.css";

.tip-body :deep(p) {
  margin: 0;
}

.tip-body :deep(h3),
.tip-body :deep(h4) {
  @apply text-heading text-sm font-semibold;
}

.tip-body :deep(ul),
.tip-body :deep(ol) {
  @apply list-outside pl-5;
}

.tip-body :deep(ul) {
  @apply list-disc;
}

.tip-body :deep(ol) {
  @apply list-decimal;
}

.tip-body :deep(a) {
  @apply text-primary-600 underline;
}

.tip-body :deep(blockquote) {
  @apply border-line-strong border-l-2 pl-3 italic;
}

.tip-body :deep(code) {
  @apply bg-panel-muted rounded-sm px-1 py-0.5 font-mono text-xs;
}

.tip-body :deep(pre) {
  @apply bg-panel-muted overflow-x-auto rounded-sm p-2 font-mono text-xs;
}
</style>
