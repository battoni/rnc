import { createApp } from 'vue';
import Libraries from '@Libraries';
import router from '@Libraries/router';
import Plugins from '@Plugins';
import App from './App.vue';

import '@Styles/main.css';

const app = createApp(App);

Libraries.forEach((library) => (library.package ? app.use(library.package, library.options) : app.use(library)));
Plugins.forEach((plugin) => app.use(plugin));

// Mount only after the router has resolved the initial route, so route guards read the
// matched record's meta (e.g. isPublic) instead of racing against an empty pending route.
router.isReady().then(() => app.mount('#app'));
