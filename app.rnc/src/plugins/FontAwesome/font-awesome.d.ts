import type ICONS from './icons';
import type { FontAwesomeIcon as FaIcon } from '@fortawesome/vue-fontawesome';

// This export is necessary to not overwrite @vue/runtime-core
export {};

declare module '@vue/runtime-core' {
  export interface GlobalComponents {
    FaIcon: typeof FaIcon;
  }

  export interface ComponentCustomProperties {
    $icons: typeof ICONS;
  }
}
