import RawAxios from 'axios';
import type { AxiosInstance } from 'axios';
import { errorHandlerHelper } from '@Providers';

const axios: AxiosInstance = RawAxios;

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

axios.interceptors.response.use(
  (response) => response,
  (error) => errorHandlerHelper(error)
);

export { axios };
