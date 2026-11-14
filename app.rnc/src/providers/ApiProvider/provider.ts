import type { ApiParams, ApiQuery, ApiQueryValue } from './interfaces';
import { useAppBusy, useGlobalAbortController } from '@Composables';
import { API_URL } from '@Constants';
import { currentFirmSlug } from '@Helpers';
import { axios } from '@Libraries';
// From its own module, not the `@Libraries` barrel: the barrel loops back through `@UserModule` to
// this file, and in a view's integration spec `i18n` arrived undefined and every request threw.
import i18n from '@Libraries/i18n';
import { isCanceledRequest } from './errorHandler.helper';

const DEFAULT_AXIOS_OPTIONS = {
  validateStatus: (status: number) => status >= 200 && status < 300,
};

/**
 * `query` becomes the query string, so no service concatenates one by hand. An empty value is
 * dropped rather than sent as `?key=` — the API reads `?status=` as "filter by the empty status",
 * not as "no filter". An array is sent as `key[]=a&key[]=b`, which is how Laravel reads a list.
 * A boolean travels as `1` / `0`: Laravel's `boolean` rule refuses the string `"true"`, so
 * `String(true)` turned a filter into a 422.
 */
function buildQuery(query?: ApiQuery): string {
  if (!query) return '';

  const searchParams = new URLSearchParams();

  Object.entries(query).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      value.filter(isPresent).forEach((entry) => searchParams.append(`${key}[]`, toQueryValue(entry)));
      return;
    }

    if (isPresent(value)) searchParams.set(key, toQueryValue(value));
  });

  const queryString = searchParams.toString();

  return queryString ? `?${queryString}` : '';
}

function toQueryValue(value: ApiQueryValue): string {
  if (typeof value === 'boolean') return value ? '1' : '0';

  return String(value);
}

function isPresent(value: unknown): boolean {
  return value !== undefined && value !== null && value !== '';
}

export default async function (params: ApiParams) {
  const { forwardError, headers, isUpload, method, payload } = params;

  const endpoint = `${API_URL}/${params.endpoint}${buildQuery(params.query)}`;

  // rnc-4 R2: the host names the firm, on every call. Set AFTER a service's own headers, so no
  // service can move a call to another firm; on the root there is none and the API answers the
  // platform firm.
  const FIRM_SLUG = currentFirmSlug();

  const { getSignal } = useGlobalAbortController();

  // Every request feeds the shared app-busy counter; `.finally` guarantees the pair even when
  // the call rejects or the global controller aborts it.
  const { beginRequest, endRequest } = useAppBusy();

  const axiosOptions = {
    ...DEFAULT_AXIOS_OPTIONS,
    method,
    data: payload,
    forwardError,
    // The API's own human-facing strings (a verification e-mail's subject, for one) follow the
    // language the person is reading the app in, not the server's default.
    headers: {
      'Accept-Language': i18n.global.locale.value,
      'Content-Type': isUpload ? 'multipart/form-data' : 'application/json',
      ...headers,
      ...(FIRM_SLUG ? { 'X-Firm': FIRM_SLUG } : {}),
    },
    signal: getSignal(),
  };

  beginRequest();

  /*
   * `finally` BEFORE `catch`, and the order is load-bearing: the busy counter must be paid back
   * whatever happened, and the promise below never settles.
   *
   * A cancelled request is answered with a promise that stays pending forever, because there is
   * no honest value to give a caller who is already gone. Rejecting instead ran every view's
   * `.catch` on the way out of the page: leaving Home during a logout put "Something went wrong"
   * on the login screen, for a request the app itself had called off. And a resolved value would
   * be worse — the view would render it as an answer.
   */
  return axios(endpoint, { ...axiosOptions })
    .then(({ data }: any) => data)
    .finally(endRequest)
    .catch((error: any) => {
      if (isCanceledRequest(error)) return new Promise(() => {});

      throw error;
    });
}
