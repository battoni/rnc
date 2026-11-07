<script setup lang="ts">
import { useId } from 'vue';
import type { AddressFields } from '@Interfaces';
import { useCepLookup } from '@Composables';
import { BRAZIL_STATES } from '@Constants';

const model = defineModel<AddressFields>({ required: true });

const fieldId = useId();
const { isLookingUp, lookupCep, lookupFailed } = useCepLookup();

// EVENTS
function onCepComplete() {
  lookupCep(model.value.cep).then((address) => {
    if (!address) return;

    model.value.street = address.logradouro || model.value.street;
    model.value.district = address.bairro || model.value.district;
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
        {{ $t('address.cep') }}
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
          :for="`${fieldId}-street`"
        >
          {{ $t('address.street') }}
        </label>

        <InputText
          v-model="model.street"
          fluid
          :id="`${fieldId}-street`"
          autocomplete="address-line1"
          name="street"
          :placeholder="$t('address.streetPlaceholder')"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          :for="`${fieldId}-number`"
        >
          {{ $t('address.number') }}
        </label>

        <InputText
          v-model="model.number"
          fluid
          :id="`${fieldId}-number`"
          name="number"
          :placeholder="$t('address.numberPlaceholder')"
        />
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        :for="`${fieldId}-complement`"
      >
        {{ $t('address.complement') }} ({{ $t('address.optional') }})
      </label>

      <InputText
        v-model="model.complement"
        fluid
        :id="`${fieldId}-complement`"
        autocomplete="address-line2"
        name="complement"
        :placeholder="$t('address.complementPlaceholder')"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        :for="`${fieldId}-district`"
      >
        {{ $t('address.district') }}
      </label>

      <InputText
        v-model="model.district"
        fluid
        :id="`${fieldId}-district`"
        autocomplete="address-level3"
        name="district"
        :placeholder="$t('address.districtPlaceholder')"
      />
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
  </div>
</template>
