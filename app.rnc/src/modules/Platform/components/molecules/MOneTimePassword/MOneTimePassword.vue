<script setup lang="ts">
import { ref } from 'vue';

/*
 * A password the API answers once (rnc-4 R25): shown here with a copy button and a line saying it
 * will not be shown again. It is never stored — the form that got it keeps it in a ref until it closes.
 */
const props = defineProps<{
  password: string;
}>();

const COPIED_FEEDBACK_MS = 2000;

const copyFailed = ref(false);
const hasCopied = ref(false);

// EVENTS
/*
 * A browser can refuse the clipboard (no permission, an insecure origin where `navigator.clipboard` is missing):
 * the password is shown only this once, so the refusal is said beside it — copy it by hand (rnc-4 verify P2-S6).
 */
function onCopy() {
  Promise.resolve()
    .then(() => navigator.clipboard.writeText(props.password))
    .then(onCopied)
    .catch(() => (copyFailed.value = true));
}

function onCopied() {
  copyFailed.value = false;
  hasCopied.value = true;

  setTimeout(() => (hasCopied.value = false), COPIED_FEEDBACK_MS);
}
</script>

<template>
  <section
    class="bg-panel-muted flex flex-col gap-3 rounded-lg p-4"
    data-testid="password-once"
  >
    <span class="text-muted text-sm">{{ $t('platform.password.title') }}</span>

    <div class="flex flex-wrap items-center gap-3">
      <code
        class="text-heading font-mono text-lg font-semibold break-all"
        data-testid="password-value"
      >
        {{ password }}
      </code>

      <Button
        data-testid="password-copy"
        severity="secondary"
        size="small"
        :icon="hasCopied ? 'pi pi-check' : 'pi pi-copy'"
        :label="hasCopied ? $t('platform.password.copied') : $t('platform.password.copy')"
        @click="onCopy"
      />
    </div>

    <p
      v-if="copyFailed"
      class="text-danger-700 text-sm"
      data-testid="password-copy-failed"
      role="alert"
    >
      {{ $t('platform.password.copyFailed') }}
    </p>

    <p
      class="text-body text-sm"
      role="status"
    >
      {{ $t('platform.password.once') }}
    </p>
  </section>
</template>
