<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LeadTimelineEvent } from '../../../interfaces';
import { translateError } from '@Helpers';
import { AddLeadNoteService } from '../../../services';

/* A team note on the lead (rnc-1 R19): it owns the call and hands the saved line to the detail. */
interface NoteError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onSuccess: [note: LeadTimelineEvent];
}>();

const props = defineProps<{
  leadId: number;
}>();

const error = ref<string | null>(null);
const isSaving = ref(false);
const text = ref('');

const canSave = computed(() => text.value.trim() !== '' && !isSaving.value);

// EVENTS
function onSubmit() {
  if (!canSave.value) return;

  const payload = { text: text.value.trim() };

  isSaving.value = true;
  error.value = null;

  AddLeadNoteService(props.leadId, payload)
    .then((response) => {
      text.value = '';
      emit('onSuccess', response.data);
    })
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: NoteError) {
  const firstError = Object.values(failure.response?.data?.errors ?? {})[0]?.[0];

  error.value = firstError
    ? translateError(firstError)
    : (failure.translatedMessage ?? translateError('errors.unexpected'));
}
</script>

<template>
  <form
    class="flex flex-col gap-3"
    data-testid="note-form"
    @submit.prevent="onSubmit"
  >
    <label
      class="text-muted mb-2 block text-sm"
      for="lead-note"
    >
      {{ $t('pipeline.lead.notes.label') }}
    </label>

    <Textarea
      v-model="text"
      autoResize
      id="lead-note"
      class="w-full"
      data-testid="note-input"
      rows="3"
      :invalid="!!error"
      :placeholder="$t('pipeline.lead.notes.placeholder')"
    />

    <AFormError :error />

    <Button
      class="self-end"
      data-testid="note-save"
      icon="pi pi-check"
      type="submit"
      :disabled="!canSave"
      :label="$t('pipeline.lead.notes.save')"
      :loading="isSaving"
    />
  </form>
</template>
