<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Idea, IdeaStatusPayload } from '../../../interfaces';
import { IDEA_STATUS_OPTIONS } from '../../../constants';
import { IDEA_STATUS } from '../../../enums';

/**
 * The maintainer's controls on one idea: what it is, and what to say about it.
 *
 * Status and note are ONE gesture, submitted together, because a status changed without a sentence
 * is the board going quiet in a new colour. The note is required for `not_now` for the same
 * reason — declining is the answer people most need explained, and an unexplained refusal is the
 * thing that stops a board being written to.
 */
const emit = defineEmits<{
  onHide: [];
  onMerge: [];
  onSubmit: [payload: IdeaStatusPayload];
}>();

const props = withDefaults(
  defineProps<{
    idea: Idea;
    isSaving?: boolean;
  }>(),
  { isSaving: false }
);

const { t } = useI18n();

const adminNote = ref(props.idea.adminNote ?? '');
const status = ref<IDEA_STATUS>(props.idea.status);

const requiresNote = computed(() => status.value === IDEA_STATUS.NOT_NOW);
const isValid = computed(() => !requiresNote.value || adminNote.value.trim().length > 0);

const statusOptions = computed(() =>
  IDEA_STATUS_OPTIONS.map((option) => ({ label: t(option.labelKey), value: option.value }))
);

// EVENTS

function onHideClick() {
  emit('onHide');
}

function onMergeClick() {
  emit('onMerge');
}

function onSubmit() {
  if (!isValid.value) return;

  emit('onSubmit', {
    status: status.value,
    adminNote: adminNote.value.trim() || null,
  });
}
</script>

<template>
  <section
    class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-4"
    data-testid="idea-moderation"
  >
    <h2 class="text-heading text-sm font-semibold">
      {{ $t('ideas.moderation.title') }}
    </h2>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="idea-moderation-status"
      >
        {{ $t('ideas.moderation.status') }}
      </label>

      <Select
        v-model="status"
        fluid
        inputId="idea-moderation-status"
        optionLabel="label"
        optionValue="value"
        :options="statusOptions"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        for="idea-moderation-note"
        :class="['mb-2 block text-sm', requiresNote && !isValid ? 'text-danger-700' : 'text-muted']"
      >
        {{ $t('ideas.moderation.note') }}
      </label>

      <Textarea
        v-model="adminNote"
        autoResize
        fluid
        id="idea-moderation-note"
        :placeholder="$t('ideas.moderation.notePlaceholder')"
        :rows="3"
      />

      <p
        v-if="requiresNote"
        class="text-muted text-xs"
      >
        {{ $t('ideas.moderation.noteRequired') }}
      </p>
    </div>

    <div class="flex flex-wrap gap-3">
      <Button
        :disabled="!isValid"
        :label="$t('ideas.moderation.save')"
        :loading="props.isSaving"
        @click="onSubmit"
      />

      <Button
        severity="secondary"
        :icon="props.idea.hidden ? 'pi pi-eye' : 'pi pi-eye-slash'"
        :label="props.idea.hidden ? $t('ideas.moderation.unhide') : $t('ideas.moderation.hide')"
        @click="onHideClick"
      />

      <Button
        icon="pi pi-object-group"
        severity="secondary"
        :label="$t('ideas.moderation.merge')"
        @click="onMergeClick"
      />
    </div>
  </section>
</template>
