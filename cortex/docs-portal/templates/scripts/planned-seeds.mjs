// Planned seeds: tables, endpoints and views of a phase that is specified but not built yet.
//
// The extractors document what EXISTS. A phased build also wants the portal to show what is coming,
// marked as such — so a table, endpoint or view declared here is merged ONLY where the real extractor
// found nothing, with `status: 'planned'`. When the phase is built, the real extraction overwrites the
// seed. Empty in the bootstrap; a project fills it from its phase specs.
//
//   export const PLANNED_TABLES = { orders: { module: 'orders', status: 'planned', columns: [...], indexes: [] } };
//   export const PLANNED_ENDPOINTS = [{ verb: 'GET', path: '/orders', controller: null, action: null, module: 'orders', permission: 'orders.read', auth: true, status: 'planned' }];
//   export const PLANNED_VIEWS = [{ routeName: 'orders', module: 'orders', component: 'OrdersView', reachedVia: 'nav', gate: 'orders.read' }];

export const PLANNED_TABLES = {};

export const PLANNED_ENDPOINTS = [];

export const PLANNED_VIEWS = [];
