<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PlatformFirm } from '../../../interfaces';
import type { FIRM_MODULE } from '@Enums';
import { translateError } from '@Helpers';
import {
  PLATFORM_CORE_MODULES,
  PLATFORM_ERROR_KEYS,
  PLATFORM_MODULE_DEPENDENCIES,
  PLATFORM_OPERATOR_ONLY_MODULES,
  PLATFORM_OPT_IN_MODULES,
} from '../../../constants';
import { UpdatePlatformFirmModulesService } from '../../../services';

/*
 * A firm's modules from the console (rnc-4 R16–R18, R25): core is always on and only listed; the
 * opt-in ones are switches; the operator-only channel sits apart. A module switched on without what
 * it needs says so beside its switch, and the save waits until it is met — the API would refuse it
 * (`modules.missingDependency`). The save sends the WHOLE list in the catalogue's order, as
 * `FirmModulesService::set` takes it, and hands the stored firm to the view.
 */
interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [firm: PlatformFirm];
}>();

const props = defineProps<{
  firm: PlatformFirm;
}>();

const CATALOGUE: FIRM_MODULE[] = [...PLATFORM_OPT_IN_MODULES, ...PLATFORM_OPERATOR_ONLY_MODULES];

const { t } = useI18n();

const enabled = ref<string[]>([...props.firm.modules]);
const formError = ref<string | null>(null);
const isSaving = ref(false);

const missing = computed(
  () =>
    Object.fromEntries(
      CATALOGUE.filter((key) => enabled.value.includes(key)).flatMap((key) => {
        const needs = (PLATFORM_MODULE_DEPENDENCIES[key] ?? []).filter((need) => !enabled.value.includes(need));

        return needs.length > 0 ? [[key, needs]] : [];
      })
    ) as Partial<Record<FIRM_MODULE, FIRM_MODULE[]>>
);

// HELPERS
function nameOf(key: string): string {
  return t(`platform.modules.names.${key}`);
}

function dependencyNote(key: FIRM_MODULE): string {
  const needs = missing.value[key];

  return needs ? t('platform.modules.needs', { module: nameOf(key), needs: needs.map(nameOf).join(', ') }) : '';
}

function isOn(key: string): boolean {
  return enabled.value.includes(key);
}

// EVENTS
function onToggle(key: FIRM_MODULE, isChecked: boolean) {
  formError.value = null;

  enabled.value = isChecked ? [...enabled.value, key] : enabled.value.filter((current) => current !== key);
}

function onSubmit() {
  const blocked = Object.keys(missing.value) as FIRM_MODULE[];

  if (blocked.length > 0) {
    formError.value = blocked.map(dependencyNote).join(' ');
    return;
  }

  const PAYLOAD = { modules: CATALOGUE.filter((key) => enabled.value.includes(key)) };

  formError.value = null;
  isSaving.value = true;

  UpdatePlatformFirmModulesService(props.firm.id, PAYLOAD)
    .then((response) => emit('onSuccess', response.data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: SaveError) {
  const code = Object.values(failure.response?.data?.errors ?? {})[0]?.[0];

  if (!code) {
    formError.value = failure.translatedMessage ?? translateError('errors.unexpected');
    return;
  }

  const key = PLATFORM_ERROR_KEYS[code];

  formError.value = key ? t(key) : translateError(code);
}
</script>

<template>
  <form
    novalidate
    id="firm-modules-form"
    class="mx-auto flex w-full max-w-[560px] flex-col gap-5"
    data-testid="modules-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body text-sm">{{ $t('platform.modules.lede', { name: firm.name }) }}</p>

    <section class="flex flex-col gap-2">
      <h3 class="text-heading text-sm font-semibold">{{ $t('platform.modules.core') }}</h3>

      <ul
        class="flex flex-wrap gap-2"
        data-testid="modules-core"
      >
        <li
          v-for="key in PLATFORM_CORE_MODULES"
          class="bg-panel-muted text-body rounded-sm px-2 py-1 text-xs font-semibold"
          :key
        >
          {{ nameOf(key) }}
        </li>
      </ul>

      <p class="text-muted text-xs">{{ $t('platform.modules.coreHint') }}</p>
    </section>

    <section class="flex flex-col gap-3">
      <h3 class="text-heading text-sm font-semibold">{{ $t('platform.modules.optIn') }}</h3>

      <ul
        class="divide-line flex flex-col divide-y"
        data-testid="modules-opt-in"
      >
        <li
          v-for="key in PLATFORM_OPT_IN_MODULES"
          class="flex flex-col gap-1 py-3"
          :key
        >
          <div class="flex items-center justify-between gap-3">
            <label
              class="text-body min-w-0 text-sm break-words"
              :for="`module-${key}`"
            >
              {{ nameOf(key) }}
            </label>

            <ToggleSwitch
              :data-testid="`module-switch-${key}`"
              :inputId="`module-${key}`"
              :modelValue="isOn(key)"
              @update:modelValue="onToggle(key, $event)"
            />
          </div>

          <p
            v-if="missing[key]"
            class="text-danger-700 text-xs"
            :data-testid="`module-dependency-${key}`"
          >
            {{ dependencyNote(key) }}
          </p>
        </li>
      </ul>
    </section>

    <section
      class="flex flex-col gap-3"
      data-testid="modules-operator-only"
    >
      <h3 class="text-heading text-sm font-semibold">{{ $t('platform.modules.operatorOnly') }}</h3>

      <p class="text-muted text-xs">{{ $t('platform.modules.operatorOnlyHint') }}</p>

      <div
        v-for="key in PLATFORM_OPERATOR_ONLY_MODULES"
        class="flex items-center justify-between gap-3"
        :key
      >
        <label
          class="text-body min-w-0 text-sm break-words"
          :for="`module-${key}`"
        >
          {{ nameOf(key) }}
        </label>

        <ToggleSwitch
          :data-testid="`module-switch-${key}`"
          :inputId="`module-${key}`"
          :modelValue="isOn(key)"
          @update:modelValue="onToggle(key, $event)"
        />
      </div>
    </section>

    <AFormError
      data-testid="modules-error"
      :error="formError"
    />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[560px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('platform.form.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="modules-save"
      form="firm-modules-form"
      type="submit"
      :label="$t('platform.form.save')"
      :loading="isSaving"
    />
  </div>
</template>
