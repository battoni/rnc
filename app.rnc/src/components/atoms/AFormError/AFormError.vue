<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

const props = defineProps<{
  error?: string | null;
}>();

const { t } = useI18n();
const { name } = useRoute();
const toast = useToast();

const isCopied = ref(false);

const isUnexpectedError = computed(() => {
  if (!props.error) return false;

  const unexpectedPattern = t('errors.unexpected');
  const parts = unexpectedPattern.split('{error}');
  const introText = parts[0];

  return !introText ? false : props.error.includes(introText.trim());
});

const errorParts = computed(() => {
  if (!props.error || !isUnexpectedError.value) return null;

  const unexpectedPattern = t('errors.unexpected');
  const parts = unexpectedPattern.split('{error}');
  const introText = parts[0];

  if (!introText) return null;

  const actualError = props.error.replace(introText.trim(), '').trim();

  return {
    intro: introText.trim(),
    error: actualError,
  };
});

// EVENTS
function onCopyClipboardReset() {
  isCopied.value = false;
}

function onCopyClipboardSuccess() {
  isCopied.value = true;
  setTimeout(onCopyClipboardReset, 2000);
}

function copyError() {
  if (!errorParts.value?.error) return;

  const routeName = name ? `\n\nRoute: ${String(name)}` : '';
  const errorWithRoute = `${errorParts.value.error}${routeName}`;

  navigator.clipboard
    .writeText(errorWithRoute)
    .then(onCopyClipboardSuccess)
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        life: 5000,
      })
    );
}
</script>

<template>
  <div
    v-if="error"
    class="w-full"
  >
    <div v-if="isUnexpectedError && errorParts">
      <p class="text-muted mb-2 text-sm">
        {{ errorParts.intro }}
      </p>

      <Message
        class="max-w-full"
        severity="error"
        size="small"
        variant="simple"
      >
        <template #icon>
          <button
            class="touch-target cursor-pointer"
            type="button"
            :aria-label="$t(isCopied ? 'errors.copied' : 'errors.copy')"
            @click="copyError"
          >
            <i
              aria-hidden="true"
              :class="isCopied ? 'pi pi-check' : 'pi pi-copy'"
            />
          </button>
        </template>

        {{ errorParts.error }}
      </Message>
    </div>

    <Message
      v-else
      class="max-w-full"
      severity="error"
      size="small"
      variant="simple"
    >
      {{ error }}
    </Message>
  </div>
</template>
