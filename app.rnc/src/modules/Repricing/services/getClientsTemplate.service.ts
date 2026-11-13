import { API } from '@Providers';

/** The import's template: the header row, as the CSV text the API serves. */
export default async function getClientsTemplate(): Promise<string> {
  return API({ endpoint: 'clients/import/template', method: 'get' });
}
