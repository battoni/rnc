const IBGE_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados';

interface IbgeMunicipio {
  nome: string;
}

/**
 * Look up Brazilian municipalities for a UF via the public IBGE API (CORS-enabled),
 * client-side — it is not part of our own API. Returns an empty sorted list when the
 * UF is blank or the request fails.
 */
export default async function getCitiesByStateService(state: string): Promise<string[]> {
  const stateCode = state.trim().toUpperCase();

  if (stateCode.length !== 2) return [];

  return fetch(`${IBGE_URL}/${stateCode}/municipios`)
    .then((response) => (response.ok ? response.json() : []))
    .then((municipios: IbgeMunicipio[]) =>
      (Array.isArray(municipios) ? municipios : [])
        .map((municipio) => municipio.nome)
        .filter(Boolean)
        .sort((left, right) => left.localeCompare(right, 'pt-BR'))
    )
    .catch(() => []);
}
