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

const hasCopied = ref(false);

// EVENTS
function onCopy() {
  navigator.clipboard.writeText(props.password).then(onCopied);
}

function onCopied() {
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
      class="text-body text-sm"
      role="status"
    >
      {{ $t('platform.password.once') }}
    </p>
  </section>
</template>
