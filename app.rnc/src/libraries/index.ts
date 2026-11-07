// NOTE: import order here is load-bearing. `./router` (router/index.ts) attaches
// attachAuthGuard, which pulls @UserModule -> @Providers -> @Libraries and loops back to
// this module. Evaluating `./axios` first transitively loads @UserModule, so it is ready
// when `./router` runs the guard — otherwise `router` would be captured here as `undefined`.
// ESLint sorts these alphabetically (axios < router), which preserves it; do not hand-reorder.
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import { axios } from './axios';
import i18n from './i18n';
import Pinia from './pinia';
import { resetAllStores } from './pinia';
import PrimeVuePlugin from './primevue';
import router from './router';

type Library = { package: any; options?: any } | any;

const libraries: Library[] = [i18n, Pinia, PrimeVuePlugin, ConfirmationService, ToastService, router];

export default libraries;
export { axios, resetAllStores };
