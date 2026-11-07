import type { App } from 'vue';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import type { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import ICONS from './icons';

export default {
  install: (app: App) => {
    const iconDefinitions = Object.values(ICONS).filter((icon): icon is IconDefinition => typeof icon !== 'string');

    library.add(...iconDefinitions);

    app.component('FaIcon', FontAwesomeIcon);
    app.config.globalProperties.$icons = ICONS;
  },
};
