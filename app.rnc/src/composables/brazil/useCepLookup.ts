import { readonly, ref } from 'vue';
import type { ViaCepAddress } from '@Interfaces';
import { GetAddressByCepService } from '@Services';

/**
 * One CEP lookup at a time, for a field block that fills an address from ViaCEP.
 *
 * The service never throws: an unknown CEP and a failed request both answer `null`, and both mean
 * the same thing to the person — type the address by hand — so `lookupFailed` does not tell them
 * apart. A lookup that a newer one overtook resolves to `null` and touches no state: a slow answer
 * for the first CEP typed must not fill the form after the second.
 */
function useCepLookup() {
  let latestLookup = 0;

  const isLookingUp = ref(false);
  const lookupFailed = ref(false);

  // EVENTS
  function lookupCep(cep: string): Promise<null | ViaCepAddress> {
    latestLookup += 1;

    const lookup = latestLookup;

    isLookingUp.value = true;
    lookupFailed.value = false;

    return GetAddressByCepService(cep).then((address) => {
      if (lookup !== latestLookup) return null;

      isLookingUp.value = false;
      lookupFailed.value = !address;

      return address;
    });
  }

  return { isLookingUp: readonly(isLookingUp), lookupCep, lookupFailed: readonly(lookupFailed) };
}

export { useCepLookup };
