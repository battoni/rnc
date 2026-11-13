<script setup lang="ts">
import { computed, ref } from 'vue';
import type { CnpjCompany } from '../../../interfaces';
import { LOOKUP_STEPS } from '../../../constants';
import { LOOKUP_STATUS } from '../../../enums';

/*
 * The CNPJ path of step 1 (rnc-1 R9): the field, the "Consultando Receita · Simples · CNAE"
 * sequence while the API asks BrasilAPI, then the company it found — or, for an unknown CNPJ or a
 * failed call, a calm way on to the quiz (D3). Never an error page.
 */
const props = defineProps<{
  company: CnpjCompany | null;
  isBusy: boolean;
  status: LOOKUP_STATUS;
  stepIndex: number;
}>();

const emit = defineEmits<{
  onLookup: [cnpj: string];
  onQuiz: [];
}>();

const cnpj = ref('');

const isRunning = computed(() => props.status === LOOKUP_STATUS.RUNNING);

const showsSequence = computed(() =>
  [LOOKUP_STATUS.FOUND, LOOKUP_STATUS.RUNNING, LOOKUP_STATUS.UNAVAILABLE].includes(props.status)
);

// HELPERS
function lineStatus(index: number): 'done' | 'running' | 'waiting' {
  if (index < props.stepIndex || !isRunning.value) return 'done';

  return index === props.stepIndex ? 'running' : 'waiting';
}

// EVENTS
function onSubmit() {
  emit('onLookup', cnpj.value);
}
</script>

<template>
  <div class="flex flex-col gap-5">
    <form
      class="flex flex-col gap-2"
      @submit.prevent="onSubmit"
    >
      <label
        class="text-muted mb-2 block text-sm"
        for="diagnostic-cnpj"
      >
        {{ $t('diagnostic.cnpj.label') }}
      </label>

      <div class="flex flex-col gap-3 sm:flex-row">
        <InputText
          v-model="cnpj"
          id="diagnostic-cnpj"
          autocomplete="off"
          class="min-w-0 flex-1"
          data-testid="cnpj-input"
          inputmode="numeric"
          :disabled="isRunning || props.isBusy"
          :placeholder="$t('diagnostic.cnpj.placeholder')"
        />

        <Button
          data-testid="cnpj-lookup"
          icon="pi pi-search"
          type="submit"
          :disabled="props.isBusy"
          :label="$t('diagnostic.cnpj.lookup')"
          :loading="isRunning"
        />
      </div>
    </form>

    <p
      v-if="props.status === LOOKUP_STATUS.INVALID"
      class="text-danger-700 text-sm"
      data-testid="cnpj-invalid"
      role="alert"
    >
      {{ $t('diagnostic.cnpj.invalid') }}
    </p>

    <div
      v-if="showsSequence"
      aria-live="polite"
      class="bg-panel-muted flex flex-col gap-3 rounded-lg p-4"
      data-testid="lookup"
    >
      <p class="text-heading text-sm font-semibold">
        {{ $t('diagnostic.cnpj.sequenceTitle') }}
      </p>

      <ol class="flex flex-col gap-2">
        <li
          v-for="(line, index) in LOOKUP_STEPS"
          class="flex items-center gap-3 text-sm"
          data-testid="lookup-step"
          :key="line"
          :data-status="lineStatus(index)"
        >
          <i
            aria-hidden="true"
            :class="[
              'pi shrink-0 text-sm',
              lineStatus(index) === 'done' && 'pi-check-circle text-success-600',
              lineStatus(index) === 'running' && 'pi-spin pi-spinner text-primary-600',
              lineStatus(index) === 'waiting' && 'pi-circle text-subtle',
            ]"
          />

          <span class="flex min-w-0 flex-1 flex-col">
            <strong class="text-heading">
              {{ $t(`diagnostic.cnpj.sources.${line}.name`) }}
            </strong>

            <span class="text-muted text-xs">
              {{ $t(`diagnostic.cnpj.sources.${line}.what`) }}
            </span>
          </span>

          <span class="text-muted shrink-0 text-xs">
            {{ $t(`diagnostic.cnpj.lineStatus.${lineStatus(index)}`) }}
          </span>
        </li>
      </ol>
    </div>

    <div
      v-if="props.status === LOOKUP_STATUS.UNAVAILABLE"
      class="bg-info-50 text-body flex flex-col items-start gap-3 rounded-lg p-4 text-sm"
      data-testid="cnpj-fallback"
      role="status"
    >
      <p>{{ $t('diagnostic.cnpj.fallback') }}</p>

      <Button
        data-testid="cnpj-fallback-quiz"
        severity="secondary"
        :label="$t('diagnostic.cnpj.fallbackAction')"
        @click="emit('onQuiz')"
      />
    </div>

    <dl
      v-if="props.status === LOOKUP_STATUS.FOUND && props.company"
      class="bg-panel shadow-lift grid grid-cols-1 gap-x-6 gap-y-3 rounded-lg p-5 text-sm sm:grid-cols-2"
      data-testid="company-card"
    >
      <div class="flex flex-col gap-0.5 sm:col-span-2">
        <dt class="text-muted text-xs">{{ $t('diagnostic.cnpj.company') }}</dt>

        <dd class="text-heading font-semibold break-words">{{ props.company.name }}</dd>
      </div>

      <div class="flex flex-col gap-0.5 sm:col-span-2">
        <dt class="text-muted text-xs">{{ $t('diagnostic.cnpj.cnae') }}</dt>

        <dd class="text-body break-words">
          {{ props.company.cnae }}{{ props.company.cnae_description ? ` · ${props.company.cnae_description}` : '' }}
        </dd>
      </div>

      <div class="flex flex-col gap-0.5">
        <dt class="text-muted text-xs">{{ $t('diagnostic.cnpj.sector') }}</dt>

        <dd class="text-body">{{ $t(`diagnostic.sectors.${props.company.sector}`) }}</dd>
      </div>

      <div class="flex flex-col gap-0.5">
        <dt class="text-muted text-xs">{{ $t('diagnostic.cnpj.porte') }}</dt>

        <dd class="text-body">{{ props.company.porte ?? $t('diagnostic.cnpj.unknown') }}</dd>
      </div>

      <div class="flex flex-col gap-0.5">
        <dt class="text-muted text-xs">{{ $t('diagnostic.cnpj.regime') }}</dt>

        <dd class="text-body">
          {{
            props.company.regime ? $t(`diagnostic.regimes.${props.company.regime}`) : $t('diagnostic.cnpj.askedNext')
          }}
        </dd>
      </div>
    </dl>
  </div>
</template>
