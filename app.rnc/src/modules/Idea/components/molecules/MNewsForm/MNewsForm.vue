<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { NewsEntry, NewsPayload } from '../../../interfaces';
import type { IdeaAreaValue } from '../../../types';
import { IDEA_AREA_OPTIONS, NEWS_COVER_ACCEPT, NEWS_COVER_MAX_BYTES } from '../../../constants';

/**
 * Write a Novidades entry: a headline, the line that earns the click, a cover, the article, and
 * where it sends the reader afterwards.
 *
 * The toolbar is deliberately NARROWER than the editor could offer, and it matches the server's
 * allow-list rather than exceeding it. A button that inserts something the sanitiser then discards
 * is worse than no button: the writer sees it work, saves, and finds it gone. Image is the one
 * that matters — the entry has a cover, and an `<img>` in the body would be a remote URL nobody
 * vetted loading in every reader's browser.
 */
const emit = defineEmits<{
  onClose: [];
  onSubmit: [payload: NewsPayload];
}>();

const props = withDefaults(
  defineProps<{
    /** Null creates; an entry edits. */
    entry?: NewsEntry | null;
    deliveredIdeaOptions?: { label: string; value: number }[];
    isSaving?: boolean;
  }>(),
  { entry: null, deliveredIdeaOptions: () => [], isSaving: false }
);

const { t } = useI18n();

const FORM_ID = 'news-form';

/* The Ideas board's own list, not a second one: an idea about a screen and the entry announcing it
   must not be filed under two different words. */
const areaOptions = computed(() => IDEA_AREA_OPTIONS.map(({ labelKey, value }) => ({ label: t(labelKey), value })));

const areas = ref<IdeaAreaValue[]>(props.entry?.areas ?? []);
const body = ref(props.entry?.body ?? '');
const coverFile = ref<File | null>(null);
const ideaId = ref<number | null>(props.entry?.idea?.id ?? null);
const isCoverCleared = ref(false);
const link = ref(props.entry?.link ?? '');
const publish = ref(props.entry?.published ?? true);
const subtitle = ref(props.entry?.subtitle ?? '');
const title = ref(props.entry?.title ?? '');

/** The editor emits `<p><br></p>` for an empty document, so emptiness is measured on the text. */
const bodyText = computed(() => body.value.replace(/<[^>]*>/g, '').trim());
const currentCoverUrl = computed(() => (isCoverCleared.value ? null : (props.entry?.cover?.url ?? null)));
const isEditing = computed(() => Boolean(props.entry));
const isValid = computed(() => title.value.trim().length > 0 && bodyText.value.length > 0);

/*
 * The button says what the click will do, which is the switch's business and not only the
 * dialog's. Reading `isEditing` alone, a writer who flipped the toggle to Rascunho was still
 * offered "Publicar" — the two controls sat side by side contradicting each other, and the one
 * that decides is the quieter of the two.
 */
const submitLabelKey = computed(() => {
  if (!publish.value) return 'news.actions.saveDraft';

  return isEditing.value ? 'news.actions.save' : 'news.actions.publishAction';
});

// EVENTS

/** A cleared picker on an entry that HAS a cover means "delete it", not "leave it alone". */
function onCoverChange(file: File | null) {
  coverFile.value = file;
  isCoverCleared.value = file === null;
}

function onCancelClick() {
  emit('onClose');
}

function onSubmit() {
  if (!isValid.value) return;

  emit('onSubmit', {
    title: title.value.trim(),
    subtitle: subtitle.value.trim() || null,
    body: body.value,
    link: link.value.trim() || null,
    areas: areas.value,
    ideaId: ideaId.value,
    publish: publish.value,
    cover: coverFile.value,
    removeCover: isCoverCleared.value && Boolean(props.entry?.cover),
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
        for="news-title"
      >
        {{ $t('news.form.title') }}
      </label>

      <InputText
        v-model="title"
        fluid
        id="news-title"
        :maxlength="140"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="news-subtitle"
      >
        {{ $t('news.form.subtitle') }}
      </label>

      <InputText
        v-model="subtitle"
        fluid
        id="news-subtitle"
        :maxlength="200"
      />

      <p class="text-muted text-xs">{{ $t('news.form.subtitleHint') }}</p>
    </div>

    <MImageUpload
      :accept="NEWS_COVER_ACCEPT"
      :currentUrl="currentCoverUrl"
      :hint="$t('news.form.coverHint')"
      :label="$t('news.form.cover')"
      :maxBytes="NEWS_COVER_MAX_BYTES"
      @onChange="onCoverChange"
    />

    <div class="flex flex-col gap-2">
      <label class="text-muted mb-2 block text-sm">{{ $t('news.form.body') }}</label>

      <!-- The toolbar is declared through PrimeVue's `#toolbar` slot, NOT through `:modules`.
           With `:modules`, Quill builds its own toolbar and PrimeVue still renders its default
           template — two rows, one of them dead markup that formats nothing.
           It is also deliberately narrower than Quill could offer, matching the server's
           allow-list rather than exceeding it: a button that inserts something the sanitiser then
           discards is worse than no button, because the writer sees it work, saves, and finds it
           gone. `image` is the one that matters — the entry has a cover, and an `<img>` here would
           be a remote URL nobody vetted loading in every reader's browser. -->
      <Editor
        v-model="body"
        data-testid="news-body-editor"
        editorStyle="height: 260px"
      >
        <template #toolbar>
          <span class="ql-formats">
            <select class="ql-header">
              <option value="2"></option>

              <option value="3"></option>

              <option selected></option>
            </select>
          </span>

          <span class="ql-formats">
            <button class="ql-bold"></button>

            <button class="ql-italic"></button>

            <button class="ql-underline"></button>

            <button class="ql-strike"></button>
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

      <p class="text-muted text-xs">{{ $t('news.form.bodyHint') }}</p>
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="news-link"
      >
        {{ $t('news.form.link') }}
      </label>

      <InputText
        v-model="link"
        fluid
        id="news-link"
        placeholder="https://"
      />

      <p class="text-muted text-xs">{{ $t('news.form.linkHint') }}</p>
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="news-areas"
      >
        {{ $t('news.form.areas') }}
      </label>

      <MultiSelect
        v-model="areas"
        fluid
        showClear
        display="chip"
        inputId="news-areas"
        optionLabel="label"
        optionValue="value"
        :options="areaOptions"
        :placeholder="$t('news.form.areasPlaceholder')"
      />

      <p class="text-muted text-xs">{{ $t('news.form.areasHint') }}</p>
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="news-idea"
      >
        {{ $t('news.form.idea') }}
      </label>

      <Select
        v-model="ideaId"
        filter
        fluid
        showClear
        inputId="news-idea"
        optionLabel="label"
        optionValue="value"
        :options="props.deliveredIdeaOptions"
        :placeholder="$t('news.form.ideaPlaceholder')"
      />

      <p class="text-muted text-xs">{{ $t('news.form.ideaHint') }}</p>
    </div>

    <div class="flex items-center gap-2">
      <ToggleSwitch
        v-model="publish"
        inputId="news-publish"
      />

      <label
        class="text-muted text-sm"
        for="news-publish"
      >
        {{ publish ? $t('news.status.published') : $t('news.status.draft') }}
      </label>

      <span
        v-if="!publish"
        class="text-muted text-xs"
      >
        {{ $t('news.draftOnly') }}
      </span>
    </div>
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      type="button"
      :label="$t('common.cancel')"
      @click="onCancelClick"
    />

    <Button
      data-testid="news-submit"
      type="submit"
      :disabled="!isValid"
      :form="FORM_ID"
      :label="$t(submitLabelKey)"
      :loading="props.isSaving"
    />
  </div>

  <div class="bg-canvas absolute inset-x-0 bottom-0 z-10 h-5" />
</template>
