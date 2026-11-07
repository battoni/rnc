// Minimal router stub used in tests via vitest.config.ts alias.
// Prevents routes.ts from spreading uninitialized module barrel exports.
// Integration specs that need real navigation inject their own router as a plugin.
import { createRouter, createMemoryHistory } from 'vue-router';

export default createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div />' } },
    { path: '/login', name: 'auth.login', component: { template: '<div />' } },
  ],
});
