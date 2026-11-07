<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * One image, chosen three ways: click, drag, or paste.
 *
 * Paste is the one worth building for. The images people actually attach to a bug report or a
 * changelog entry are screenshots, and a screenshot starts life in the clipboard — asking somebody
 * to save it to disk first, find it in a picker, and then upload it is three steps to undo
 * something the operating system already did.
 *
 * The component owns NO upload. It hands the parent a `File` and renders whatever preview it can;
 * who stores it, where, and under which validation belongs to the feature, not to a picker.
 *
 * VALIDATION HERE IS A COURTESY, NOT A BOUNDARY. The server refuses the same things and is the only
 * thing that has to be right — this exists so nobody uploads two megabytes to be told no.
 */
const emit = defineEmits<{
  onChange: [file: File | null];
}>();

const props = withDefaults(
  defineProps<{
    /** An already-stored image, so editing shows what is there rather than an empty box. */
    currentUrl?: string | null;
    label?: string;
    hint?: string;
    accept?: string;
    maxBytes?: number;
    disabled?: boolean;
  }>(),
  {
    currentUrl: null,
    label: '',
    hint: '',
    accept: 'image/jpeg,image/png,image/webp',
    maxBytes: 2 * 1024 * 1024,
    disabled: false,
  }
);

const { t } = useI18n();

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');

const error = ref<string | null>(null);
const isDragging = ref(false);
const localPreview = ref<string | null>(null);
const selectedName = ref<string | null>(null);

const acceptedTypes = computed(() => props.accept.split(',').map((type) => type.trim()));
const previewUrl = computed(() => localPreview.value ?? props.currentUrl);

onMounted(onComponentMount);
onBeforeUnmount(onComponentUnmount);

// HELPERS

/**
 * The preview is an object URL, which pins the file in memory until it is revoked. Revoking the
 * previous one on every change — and again on unmount — is what stops a form that swaps images a
 * few times from holding all of them.
 */
function setPreview(file: File | null) {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value);

  localPreview.value = file ? URL.createObjectURL(file) : null;
  selectedName.value = file?.name ?? null;
}

function isAcceptable(file: File): boolean {
  error.value = null;

  if (!acceptedTypes.value.includes(file.type)) {
    error.value = t('imageUpload.wrongType');
    return false;
  }

  if (file.size > props.maxBytes) {
    error.value = t('imageUpload.tooLarge');
    return false;
  }

  return true;
}

function take(file: File | null) {
  if (props.disabled) return;

  if (!file) {
    setPreview(null);
    emit('onChange', null);
    return;
  }

  if (!isAcceptable(file)) return;

  setPreview(file);
  emit('onChange', file);
}

// EVENTS

function onComponentMount() {
  window.addEventListener('paste', onWindowPaste);
}

function onComponentUnmount() {
  window.removeEventListener('paste', onWindowPaste);

  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
}

/**
 * Listened for on the window, not on the box, because a clipboard paste has no target to speak of:
 * asking somebody to focus a drop zone before pressing the shortcut defeats the point. The guard is
 * that it only acts on a paste that actually carries an image — pasting text into the title field
 * of the same form must not swallow the keystroke.
 */
/*
 * A paste aimed at a text field is not a cover.
 *
 * The listener is on `window` — a clipboard paste has no hover target — so with the news form open
 * a writer who copies a screenshot for somewhere else and hits Ctrl+V while the caret is in the
 * title silently replaced the cover, and on save the old file was deleted. Somebody typing into a
 * field is typing, not attaching.
 */
function isTypingSomewhereElse(): boolean {
  const ACTIVE = document.activeElement;

  if (!(ACTIVE instanceof HTMLElement)) return false;

  const TAG = ACTIVE.tagName;

  return TAG === 'INPUT' || TAG === 'TEXTAREA' || ACTIVE.isContentEditable;
}

function onWindowPaste(event: ClipboardEvent) {
  if (props.disabled) return;

  if (isTypingSomewhereElse()) return;

  const IMAGE_ITEM = [...(event.clipboardData?.items ?? [])].find((item) => item.type.startsWith('image/'));

  if (!IMAGE_ITEM) return;

  const FILE = IMAGE_ITEM.getAsFile();

  if (!FILE) return;

  event.preventDefault();
  take(FILE);
}

function onBrowseClick() {
  fileInput.value?.click();
}

/*
 * The input is emptied after every pick, and that is what makes picking the SAME file twice work.
 *
 * A file input fires `change` only when its value changes. Choose a cover, clear it, choose the
 * same file again through the picker — the value never moved, no event fired, and nothing at all
 * happened: no preview, no error, no clue. Emptying it means every pick is a change.
 */
function onInputChange(event: Event) {
  const INPUT = event.target as HTMLInputElement;

  take(INPUT.files?.[0] ?? null);

  INPUT.value = '';
}

function onDragOver() {
  if (!props.disabled) isDragging.value = true;
}

function onDragLeave() {
  isDragging.value = false;
}

function onDrop(event: DragEvent) {
  isDragging.value = false;

  take(event.dataTransfer?.files?.[0] ?? null);
}

function onClearClick() {
  error.value = null;

  // Same reason as `onInputChange`: a cleared preview with a stale input value is a picker that
  // ignores the file you just removed.
  if (fileInput.value) fileInput.value.value = '';

  take(null);
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <label
      v-if="props.label"
      class="text-muted mb-2 block text-sm"
    >
      {{ props.label }}
    </label>

    <!-- A button, not a div with a click handler: this is the control that opens the picker, so it
         has to be reachable by keyboard and announce itself as something that acts. -->
    <button
      data-testid="image-upload-dropzone"
      type="button"
      :class="[
        'border-line-strong bg-panel-muted flex w-full flex-col items-center justify-center gap-2 rounded-md border border-dashed p-4 transition-colors',
        isDragging ? 'border-primary-500' : '',
        props.disabled ? 'cursor-not-allowed opacity-60' : 'hover:border-primary-300 cursor-pointer',
      ]"
      :disabled="props.disabled"
      @click="onBrowseClick"
      @dragleave.prevent="onDragLeave"
      @dragover.prevent="onDragOver"
      @drop.prevent="onDrop"
    >
      <img
        v-if="previewUrl"
        class="border-line max-h-48 w-full rounded-sm border object-contain"
        data-testid="image-upload-preview"
        :alt="props.label || $t('imageUpload.preview')"
        :src="previewUrl"
      />

      <template v-if="!previewUrl">
        <i class="pi pi-image text-subtle text-2xl" />

        <span class="text-muted text-center text-sm">{{ $t('imageUpload.callToAction') }}</span>
      </template>

      <span
        v-if="selectedName"
        class="text-muted truncate text-xs"
      >
        {{ selectedName }}
      </span>
    </button>

    <input
      class="hidden"
      ref="fileInput"
      type="file"
      :accept="props.accept"
      @change="onInputChange"
    />

    <p
      v-if="error"
      class="text-danger-700 text-xs"
      data-testid="image-upload-error"
    >
      {{ error }}
    </p>

    <p
      v-if="!error && props.hint"
      class="text-muted text-xs"
    >
      {{ props.hint }}
    </p>

    <Button
      v-if="previewUrl && !props.disabled"
      text
      class="self-start"
      data-testid="image-upload-clear"
      icon="pi pi-times"
      severity="secondary"
      size="small"
      :label="$t('imageUpload.remove')"
      @click="onClearClick"
    />
  </div>
</template>
