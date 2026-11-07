<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Idea, IdeaPayload } from '../../../interfaces';
import {
  IDEA_ATTACHMENT_ACCEPT,
  IDEA_ATTACHMENT_MAX_BYTES,
  IDEA_AREA_OPTIONS,
  IDEA_DESCRIPTION_MAX_LENGTH,
  IDEA_TITLE_MAX_LENGTH,
  IDEA_TYPE_OPTIONS,
} from '../../../constants';
import { IDEA_AREA, IDEA_TYPE } from '../../../enums';

/**
 * Write an idea, or rewrite your own.
 *
 * The area arrives pre-answered when the quick-capture dialog opened it from a screen, and the
 * person can still change it. Asking someone to classify their own feedback before we will accept
 * it is how feedback stops arriving — so the field has a default and is never blocking.
 *
 * The image is validated here as well as on the server, and for a different reason: the server
 * refusal is the boundary, this one is the courtesy of not making somebody upload two megabytes to
 * be told no.
 */
const emit = defineEmits<{
  onClose: [];
  onSubmit: [payload: IdeaPayload];
}>();

const props = withDefaults(
  defineProps<{
    /** Null creates; an idea edits. */
    idea?: Idea | null;
    /** Pre-selected by the quick-capture dialog from the route the person was on. */
    initialArea?: IDEA_AREA | null;
    isSaving?: boolean;
  }>(),
  { idea: null, initialArea: null, isSaving: false }
);

const { t } = useI18n();

const FORM_ID = 'idea-form';

const area = ref<IDEA_AREA | null>(props.idea?.area ?? props.initialArea ?? IDEA_AREA.GENERAL);
const attachment = ref<File | null>(null);
const description = ref(props.idea?.description ?? '');
const removeAttachment = ref(false);
const title = ref(props.idea?.title ?? '');
const type = ref<IDEA_TYPE>(props.idea?.type ?? IDEA_TYPE.IMPROVEMENT);

const currentAttachmentUrl = computed(() => (removeAttachment.value ? null : (props.idea?.attachment?.url ?? null)));
const isEditing = computed(() => Boolean(props.idea));
const isValid = computed(() => title.value.trim().length > 0 && description.value.trim().length > 0);

const areaOptions = computed(() =>
  IDEA_AREA_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

const typeOptions = computed(() =>
  IDEA_TYPE_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

// EVENTS

/**
 * `null` is a deliberate removal, not "unchanged".
 *
 * MImageUpload owns the picking, the preview and the type/size refusal — including the paste path
 * — so all that is left here is the one thing the unit cannot know: whether clearing means "drop
 * the file I just chose" or "delete the one already stored".
 */
function onAttachmentChange(file: File | null) {
  attachment.value = file;
  removeAttachment.value = file === null && Boolean(props.idea?.attachment);
}

function onCancelClick() {
  emit('onClose');
}

function onSubmit() {
  if (!isValid.value) return;

  emit('onSubmit', {
    title: title.value.trim(),
    description: description.value.trim(),
    type: type.value,
    area: area.value,
    attachment: attachment.value,
    removeAttachment: removeAttachment.value,
  });
}
</script>

<template>
  <form
    :id="FORM_ID"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="idea-title"
      >
        {{ $t('ideas.form.title') }}
      </label>

      <InputText
        v-model="title"
        fluid
        id="idea-title"
        :maxlength="IDEA_TITLE_MAX_LENGTH"
        :placeholder="$t('ideas.form.titlePlaceholder')"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="idea-description"
      >
        {{ $t('ideas.form.description') }}
      </label>

      <Textarea
        v-model="description"
        autoResize
        fluid
        id="idea-description"
        :maxlength="IDEA_DESCRIPTION_MAX_LENGTH"
        :placeholder="$t('ideas.form.descriptionPlaceholder')"
        :rows="5"
      />

      <p class="text-muted text-xs">
        {{ $t('ideas.form.descriptionHint') }}
      </p>
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="idea-type"
      >
        {{ $t('ideas.form.type') }}
      </label>

      <Select
        v-model="type"
        fluid
        inputId="idea-type"
        optionLabel="label"
        optionValue="value"
        :options="typeOptions"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="idea-area"
      >
        {{ $t('ideas.form.area') }}
      </label>

      <Select
        v-model="area"
        fluid
        inputId="idea-area"
        optionLabel="label"
        optionValue="value"
        :options="areaOptions"
      />
    </div>

    <!-- The same unit Novidades uses for its cover, so an idea's screenshot can be PASTED. A
         screenshot starts in the clipboard, and asking somebody to save it to disk first is three
         steps to undo what the operating system already did. -->
    <MImageUpload
      :accept="IDEA_ATTACHMENT_ACCEPT"
      :currentUrl="currentAttachmentUrl"
      :hint="$t('ideas.form.attachmentHint')"
      :label="$t('ideas.form.attachment')"
      :maxBytes="IDEA_ATTACHMENT_MAX_BYTES"
      @onChange="onAttachmentChange"
    />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      type="button"
      :label="$t('common.cancel')"
      @click="onCancelClick"
    />

    <Button
      type="submit"
      :disabled="!isValid"
      :form="FORM_ID"
      :label="isEditing ? $t('ideas.actions.save') : $t('ideas.actions.send')"
      :loading="props.isSaving"
    />
  </div>

  <div class="bg-canvas absolute inset-x-0 bottom-0 z-10 h-5" />
</template>
