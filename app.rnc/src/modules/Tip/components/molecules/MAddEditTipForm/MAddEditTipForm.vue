<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { Tip } from '../../../interfaces';
import { TIP_ANCHORS } from '@Constants';
import { HTTP_STATUS } from '@Enums';
import { translateError } from '@Helpers';
import { CreateTipService, UpdateTipService } from '../../../services';

interface SubmitError {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [tip: Tip];
}>();

const props = withDefaults(
  defineProps<{
    /** Anchors already spoken for, so the create dropdown never offers an occupied one. */
    takenAnchorKeys?: string[];
    /** Null creates; a tip edits. */
    tip?: Tip | null;
  }>(),
  { takenAnchorKeys: () => [], tip: null }
);

const { t } = useI18n();
const toast = useToast();

const FORM_ID = 'tip-form';
/** The fields a refusal is shown under; a key for any other field goes under the form. */
const REFUSAL_FIELDS = ['anchor_key', 'change_reason', 'content'];

const anchorKey = ref(props.tip?.anchor_key ?? '');
const changeReason = ref('');
const content = ref(props.tip?.content ?? '');
const loading = ref(false);
const refusals = ref<Record<string, string>>({});

const isEditMode = computed(() => !!props.tip);
const hasContentChanged = computed(() => isEditMode.value && content.value !== props.tip?.content);
const isReasonMissing = computed(() => hasContentChanged.value && changeReason.value.trim().length === 0);
const isValid = computed(() => !!anchorKey.value && contentText.value.length > 0 && !isReasonMissing.value);

const anchorOptions = computed(() =>
  TIP_ANCHORS.filter((anchor) => isAnchorAvailable(anchor.key))
    .map((anchor) => ({
      label: `${t(anchor.sectionKey)} › ${t(anchor.labelKey)}`,
      value: anchor.key,
    }))
    .sort((first, second) => first.label.localeCompare(second.label))
);
/*
 * `<p><br></p>` is what Quill emits for an empty document, so emptiness has to be measured on the
 * text rather than on the string.
 */
const contentText = computed(() =>
  content.value
    .replace(/<[^>]*>/g, '')
    .replace(/ /g, ' ')
    .trim()
);
const otherRefusal = computed(
  () => Object.entries(refusals.value).find(([field]) => !REFUSAL_FIELDS.includes(field))?.[1] ?? null
);

// HELPERS
/** The provider attaches a translated message to the error; fall back only when it is absent. */
function getErrorMessage(error: unknown): string {
  const hasTranslated = !!error && typeof error === 'object' && 'translatedMessage' in error;

  if (!hasTranslated) return t('errors.somethingWentWrong');

  return (error as { translatedMessage?: string }).translatedMessage ?? t('errors.somethingWentWrong');
}

function isAnchorAvailable(key: string): boolean {
  if (key === props.tip?.anchor_key) return true;

  return !props.takenAnchorKeys.includes(key);
}

// EVENTS
/** A 422 names its rule: it goes under the field it is about, and no toast contradicts it. */
function onSaveError(error: SubmitError) {
  const isRefusal = error?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;

  if (isRefusal) {
    refusals.value = Object.fromEntries(
      Object.entries(error.response?.data?.errors ?? {}).map(([field, keys]) => [field, translateError(keys[0] ?? '')])
    );
    return;
  }

  toast.add({
    severity: 'error',
    summary: getErrorMessage(error),
    life: 10000,
  });
}

function onSubmit() {
  if (!isValid.value) return;

  loading.value = true;
  refusals.value = {};

  const reason = changeReason.value.trim() || null;

  const promise = isEditMode.value
    ? UpdateTipService(props.tip!.id, { anchor_key: anchorKey.value, change_reason: reason, content: content.value })
    : CreateTipService({ anchor_key: anchorKey.value, change_reason: reason, content: content.value });

  promise
    .then(({ data }) => {
      emit('onSuccess', data);

      toast.add({
        severity: 'success',
        summary: t(isEditMode.value ? 'tips.updatedSuccess' : 'tips.createdSuccess'),
        life: 5000,
      });
    })
    .catch(onSaveError)
    .finally(() => (loading.value = false));
}
</script>

<template>
  <form
    :id="FORM_ID"
    class="mx-auto flex w-full max-w-[640px] flex-col gap-4"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="tip-anchor"
      >
        {{ $t('tips.form.anchor') }} <span class="text-danger-600">*</span>
      </label>

      <Select
        v-model="anchorKey"
        filter
        fluid
        data-testid="tip-anchor"
        inputId="tip-anchor"
        optionLabel="label"
        optionValue="value"
        :options="anchorOptions"
        :placeholder="$t('tips.form.anchorPlaceholder')"
      />

      <small class="text-muted text-xs">{{ $t('tips.form.anchorHint') }}</small>

      <small
        v-if="refusals.anchor_key"
        class="text-danger-700 text-sm"
        data-testid="tip-anchor-refusal"
      >
        {{ refusals.anchor_key }}
      </small>
    </div>

    <div class="flex flex-col gap-2">
      <label class="text-muted mb-2 block text-sm">
        {{ $t('tips.form.content') }} <span class="text-danger-600">*</span>
      </label>

      <!-- The toolbar is declared through PrimeVue's `#toolbar` slot, NOT through `:modules`:
           with `:modules` Quill builds its own toolbar and PrimeVue still renders its default
           template, leaving two rows with one of them dead markup that formats nothing.

           It is also deliberately narrower than Quill could offer, matching the API's
           `TipContentSanitizer` allow-list rather than exceeding it. A button that inserts
           something the sanitiser then discards is worse than no button: the author sees it
           work, saves, and finds it gone. No image button — a tip is a few lines in a popover,
           and an <img> would be a remote URL nobody vetted loading in every operator's browser. -->
      <Editor
        v-model="content"
        data-testid="tip-content"
        editorStyle="height: 240px"
      >
        <template #toolbar>
          <span class="ql-formats">
            <button class="ql-bold"></button>

            <button class="ql-italic"></button>

            <button class="ql-underline"></button>
          </span>

          <span class="ql-formats">
            <button
              class="ql-list"
              value="ordered"
            ></button>

            <button
              class="ql-list"
              value="bullet"
            ></button>
          </span>

          <span class="ql-formats">
            <button class="ql-blockquote"></button>

            <button class="ql-code-block"></button>

            <button class="ql-link"></button>
          </span>

          <span class="ql-formats">
            <button class="ql-clean"></button>
          </span>
        </template>
      </Editor>

      <small
        v-if="refusals.content"
        class="text-danger-700 text-sm"
        data-testid="tip-content-refusal"
      >
        {{ refusals.content }}
      </small>
    </div>

    <div
      v-if="hasContentChanged"
      class="flex flex-col gap-2"
    >
      <label
        for="tip-reason"
        :class="['mb-2 block text-sm', isReasonMissing ? 'text-danger-700' : 'text-muted']"
      >
        {{ $t('tips.form.reason') }} <span class="text-danger-600">*</span>
      </label>

      <InputText
        v-model="changeReason"
        fluid
        id="tip-reason"
        data-testid="tip-reason"
        :maxlength="255"
      />

      <small class="text-muted text-xs">{{ $t('tips.form.reasonHint') }}</small>

      <small
        v-if="refusals.change_reason"
        class="text-danger-700 text-sm"
        data-testid="tip-reason-refusal"
      >
        {{ refusals.change_reason }}
      </small>
    </div>

    <small
      v-if="otherRefusal"
      class="text-danger-700 text-sm"
      data-testid="tip-form-refusal"
    >
      {{ otherRefusal }}
    </small>
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[640px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('common.actions.cancel')"
      @click="emit('onClose')"
    />

    <Button
      type="submit"
      :disabled="!isValid"
      :form="FORM_ID"
      :label="isEditMode ? $t('common.actions.edit') : $t('common.create')"
      :loading
    />
  </div>

  <div class="bg-canvas absolute bottom-0 left-0 z-10 h-5 w-full" />
</template>
