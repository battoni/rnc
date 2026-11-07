import { LEAD_EVENT_TYPE, LEAD_STAGE } from './enums';

/** Every column of the board, in order (R18): the pipeline, then Perdido. */
const BOARD_STAGES: LEAD_STAGE[] = [
  LEAD_STAGE.VISITANTE,
  LEAD_STAGE.LEAD_PARCIAL,
  LEAD_STAGE.LEAD,
  LEAD_STAGE.REUNIAO_MARCADA,
  LEAD_STAGE.PROPOSTA,
  LEAD_STAGE.CLIENTE,
  LEAD_STAGE.PERDIDO,
];

/** Each timeline type's sentence under `pipeline.lead.timeline.events` (a key cannot hold the API's dots). */
const TIMELINE_EVENT_KEYS: Record<LEAD_EVENT_TYPE, string> = {
  [LEAD_EVENT_TYPE.CNPJ_LOOKED_UP]: 'cnpjLookedUp',
  [LEAD_EVENT_TYPE.CONTACT_CAPTURED]: 'contactCaptured',
  [LEAD_EVENT_TYPE.DIAGNOSTIC_STARTED]: 'diagnosticStarted',
  [LEAD_EVENT_TYPE.MEETING_BOOKED]: 'meetingBooked',
  [LEAD_EVENT_TYPE.NOTE_ADDED]: 'noteAdded',
  [LEAD_EVENT_TYPE.REFERRAL_SENT]: 'referralSent',
  [LEAD_EVENT_TYPE.RESULT_SEEN]: 'resultSeen',
  [LEAD_EVENT_TYPE.STAGE_CHANGED]: 'stageChanged',
  [LEAD_EVENT_TYPE.STEP_REACHED]: 'stepReached',
};

/** The forward path the ◀ ▶ buttons walk (the demo's `STAGE_FLOW`); Perdido sits outside it. */
const STAGE_FLOW: LEAD_STAGE[] = BOARD_STAGES.filter((stage) => stage !== LEAD_STAGE.PERDIDO);

/** Where ◀ takes a lost lead back to (the demo's `prevStage('perdido')`). */
const STAGE_AFTER_LOST = LEAD_STAGE.LEAD;

/** The stripe that tells the columns apart at a glance — the brand's blues along the way, the states at the ends. */
const STAGE_ACCENT_CLASSES: Record<LEAD_STAGE, string> = {
  [LEAD_STAGE.CLIENTE]: 'bg-success-500',
  [LEAD_STAGE.LEAD]: 'bg-primary-400',
  [LEAD_STAGE.LEAD_PARCIAL]: 'bg-primary-300',
  [LEAD_STAGE.PERDIDO]: 'bg-danger-400',
  [LEAD_STAGE.PROPOSTA]: 'bg-primary-700',
  [LEAD_STAGE.REUNIAO_MARCADA]: 'bg-primary-500',
  [LEAD_STAGE.VISITANTE]: 'bg-line-strong',
};

/** The screens this module owns, linked by name (celer-05): the bell and the home link here. */
const PIPELINE_ROUTE_NAMES = { agenda: 'agenda', board: 'pipeline', lead: 'pipeline-lead' } as const;

/** The keys that open and change the pipeline (plan AM8). */
const PIPELINE_PERMISSIONS = { agenda: 'agenda.read', read: 'leads.read', update: 'leads.update' } as const;

/** The drag payload's type: only a card of this board is a drop the columns take. */
const LEAD_DRAG_TYPE = 'application/x-rnc-lead';

/** How long the search waits after the last key before asking the API. */
const SEARCH_DEBOUNCE_MS = 300;

/** The longest reason the API keeps (`UpdateLeadStageRequest::REASON_MAX_LENGTH`). */
const LOST_REASON_MAX_LENGTH = 300;

export {
  BOARD_STAGES,
  LEAD_DRAG_TYPE,
  LOST_REASON_MAX_LENGTH,
  PIPELINE_PERMISSIONS,
  PIPELINE_ROUTE_NAMES,
  SEARCH_DEBOUNCE_MS,
  STAGE_ACCENT_CLASSES,
  STAGE_AFTER_LOST,
  STAGE_FLOW,
  TIMELINE_EVENT_KEYS,
};
