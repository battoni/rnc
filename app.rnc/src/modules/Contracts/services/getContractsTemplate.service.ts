import { API } from '@Providers';

/** The contracts CSV's template (rnc-3 R18): the header row, as the CSV text the API serves. */
export default async function getContractsTemplate(): Promise<string> {
  return API({ endpoint: 'contracts/template', method: 'get' });
}
