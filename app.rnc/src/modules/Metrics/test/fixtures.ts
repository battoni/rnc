import type { Funnel } from '../interfaces';
import { FUNNEL_STEP, TRAFFIC_SOURCE } from '../enums';

/** The funnel of a month: 400 visits narrowing to 3 clients. */
const FUNNEL_30_FIXTURE: Funnel = {
  by_sector: [
    { agendou: 2, contato: 5, cta: 27, label: 'Comércio', slug: 'comercio', visitas: 66 },
    { agendou: 0, contato: 0, cta: 0, label: 'Agro', slug: 'agro', visitas: 0 },
  ],
  by_source: [
    { agendou: 6, cliente: 2, contato: 14, cta: 124, source: TRAFFIC_SOURCE.ORGANICO, visitas: 240 },
    { agendou: 3, cliente: 1, contato: 7, cta: 68, source: TRAFFIC_SOURCE.PAGO, visitas: 100 },
    { agendou: 1, cliente: 0, contato: 3, cta: 20, source: TRAFFIC_SOURCE.PARCEIRO, visitas: 40 },
    { agendou: 0, cliente: 0, contato: 1, cta: 8, source: TRAFFIC_SOURCE.DIRETO, visitas: 20 },
  ],
  days: 30,
  from: '2026-10-20T10:00:00-03:00',
  steps: [
    { count: 400, drop_off: null, key: FUNNEL_STEP.VISITAS },
    { count: 220, drop_off: 45, key: FUNNEL_STEP.CTA },
    { count: 40, drop_off: 81.8, key: FUNNEL_STEP.INICIO },
    { count: 25, drop_off: 37.5, key: FUNNEL_STEP.CONTATO },
    { count: 20, drop_off: 20, key: FUNNEL_STEP.RESULTADO },
    { count: 10, drop_off: 50, key: FUNNEL_STEP.AGENDOU },
    { count: 3, drop_off: 70, key: FUNNEL_STEP.CLIENTE },
  ],
  to: '2026-11-19T10:00:00-03:00',
};

/** The same funnel over the last week. */
const FUNNEL_7_FIXTURE: Funnel = {
  ...FUNNEL_30_FIXTURE,
  days: 7,
  from: '2026-11-12T10:00:00-03:00',
  steps: [
    { count: 90, drop_off: null, key: FUNNEL_STEP.VISITAS },
    { count: 30, drop_off: 66.7, key: FUNNEL_STEP.CTA },
    { count: 9, drop_off: 70, key: FUNNEL_STEP.INICIO },
    { count: 8, drop_off: 11.1, key: FUNNEL_STEP.CONTATO },
    { count: 6, drop_off: 25, key: FUNNEL_STEP.RESULTADO },
    { count: 3, drop_off: 50, key: FUNNEL_STEP.AGENDOU },
    { count: 0, drop_off: 100, key: FUNNEL_STEP.CLIENTE },
  ],
};

export { FUNNEL_30_FIXTURE, FUNNEL_7_FIXTURE };
