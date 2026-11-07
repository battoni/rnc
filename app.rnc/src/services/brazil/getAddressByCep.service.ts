import type { ViaCepAddress } from '@Interfaces';

const VIACEP_URL = 'https://viacep.com.br/ws';

/**
 * Look up a Brazilian address by CEP via the public ViaCEP API (CORS-enabled),
 * client-side — it is not part of our own API. Returns null when the CEP is
 * incomplete, unknown (`erro: true`), or the request fails.
 */
export default async function getAddressByCepService(cep: string): Promise<ViaCepAddress | null> {
  const digits = cep.replace(/\D/g, '');

  if (digits.length !== 8) return null;

  return fetch(`${VIACEP_URL}/${digits}/json/`)
    .then((response) => (response.ok ? response.json() : null))
    .then((address: ViaCepAddress | null) => (address && !address.erro ? address : null))
    .catch(() => null);
}
