import type { FUNNEL_STEP, TRAFFIC_SOURCE } from './enums';

/** One step of the funnel (`FunnelResource`): visitors who reached it, and the share of the step before lost. */
interface FunnelStepCount {
  count: number;
  /** Percent of the step before that did not come through, one decimal; null on the first step. */
  drop_off: number | null;
  key: FUNNEL_STEP;
}

/** A row of the table by source: the visitors of that first touch, step by step. */
interface FunnelSourceRow {
  agendou: number;
  cliente: number;
  contato: number;
  cta: number;
  source: TRAFFIC_SOURCE;
  visitas: number;
}

/** A row of the table by sector page: the visitors who entered on `/reforma/<slug>`. */
interface FunnelSectorRow {
  agendou: number;
  contato: number;
  cta: number;
  label: string;
  slug: string;
  visitas: number;
}

/** `GET /api/metrics/funnel?days=` (R24). */
interface Funnel {
  by_sector: FunnelSectorRow[];
  by_source: FunnelSourceRow[];
  days: number;
  from: string;
  steps: FunnelStepCount[];
  to: string;
}

export type { Funnel, FunnelSectorRow, FunnelSourceRow, FunnelStepCount };
