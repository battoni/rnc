/**
 * ViaCEP's answer, with ViaCEP's field names. Translating `logradouro` into `street` belongs to
 * whoever renders the address; the boundary does not pretend a third party speaks our words.
 */
interface ViaCepAddress {
  bairro?: string;
  cep?: string;
  erro?: boolean;
  localidade?: string;
  logradouro?: string;
  uf?: string;
}

interface StateOption {
  label: string;
  value: string;
}

/**
 * A full street address as `MAddressFields` edits it — the API's `addressColumns()` block, in our
 * words. `state` is the two-letter UF. The coordinates stay on the API: no field edits them.
 */
interface AddressFields {
  cep: string;
  city: string;
  complement: string;
  district: string;
  number: string;
  state: string;
  street: string;
}

/**
 * A place at city level as `MLocalityFields` edits it — the API's `localityColumns()` block.
 * `region` is free text inside the city, shown only when the parent asks for it.
 */
interface LocalityFields {
  cep: string;
  city: string;
  region: string;
  state: string;
}

export type { AddressFields, LocalityFields, StateOption, ViaCepAddress };
