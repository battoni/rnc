import { createPinia } from 'pinia';
import { default as resetAllStores } from './resetAllStores.helper';

const pinia = createPinia();

export default pinia;
export { resetAllStores };
