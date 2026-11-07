<script setup lang="ts">
import { useId } from 'vue';
import type { LocalityFields } from '@Interfaces';
import { useCepLookup } from '@Composables';
import { BRAZIL_STATES } from '@Constants';

defineProps<{
  showRegion?: boolean;
}>();

const model = defineModel<LocalityFields>({ required: true });

const fieldId = useId();
const { isLookingUp, lookupCep, lookupFailed } = useCepLookup();

// EVENTS
function onCepComplete() {
  lookupCep(model.value.cep).then((address) => {
    if (!address) return;

    model.value.city = address.localidade || model.value.city;
    model.value.state = address.uf || model.value.state;
  });
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        :for="`${fieldId}-cep`"
      >
        {{ $t('address.cep') }} ({{ $t('address.optional') }})
      </label>

      <InputMask
        v-model="model.cep"
        fluid
        :id="`${fieldId}-cep`"
        autocomplete="postal-code"
        inputmode="numeric"
        mask="99999-999"
        name="cep"
        :placeholder="$t('address.cepPlaceholder')"
        @complete="onCepComplete"
      />

      <small
        v-if="isLookingUp"
        class="text-muted block text-sm"
        role="status"
      >
        {{ $t('address.cepLoading') }}
      </small>

      <small
        v-else-if="lookupFailed"
        class="text-muted block text-sm"
        role="status"
      >
        {{ $t('address.cepNotFound') }}
      </small>
    </div>

    <div class="grid grid-cols-3 gap-3">
      <div class="col-span-2 flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          :for="`${fieldId}-city`"
        >
          {{ $t('address.city') }}
        </label>

        <InputText
          v-model="model.city"
          fluid
          :id="`${fieldId}-city`"
          autocomplete="address-level2"
          name="city"
          :placeholder="$t('address.cityPlaceholder')"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          :for="`${fieldId}-state`"
        >
          {{ $t('address.state') }}
        </label>

        <Select
          v-model="model.state"
          fluid
          optionLabel="label"
          optionValue="value"
          :inputId="`${fieldId}-state`"
          :options="BRAZIL_STATES"
          :placeholder="$t('address.statePlaceholder')"
        />
      </div>
    </div>

    <div
      v-if="showRegion"
      class="flex flex-col gap-2"
    >
      <label
        class="text-muted mb-2 block text-sm"
        :for="`${fieldId}-region`"
      >
        {{ $t('address.region') }} ({{ $t('address.optional') }})
      </label>

      <InputText
        v-model="model.region"
        fluid
        :id="`${fieldId}-region`"
        name="region"
        :placeholder="$t('address.regionPlaceholder')"
      />
    </div>
  </div>
</template>
