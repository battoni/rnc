// Minimal @Libraries barrel for tests.
// Exports only what tests need — intentionally omits the router import that
// triggers routes.ts → module-barrel circular-dep initialization errors.
// MSW intercepts at the network layer so the error-handler interceptor is
// not needed in integration tests.
import RawAxios from 'axios';

const axios = RawAxios.create();
axios.defaults.withCredentials = true;

export { axios };
export const resetAllStores = () => {};
export default [];
