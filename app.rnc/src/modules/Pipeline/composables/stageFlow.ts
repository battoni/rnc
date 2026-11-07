import { STAGE_AFTER_LOST, STAGE_FLOW } from '../constants';
import { LEAD_STAGE } from '../enums';

/** Where ▶ takes a card: the next stage of the flow; none from Cliente or Perdido (the demo's `nextStage`). */
function nextStage(stage: LEAD_STAGE): LEAD_STAGE | null {
  const index = STAGE_FLOW.indexOf(stage);

  return index >= 0 && index < STAGE_FLOW.length - 1 ? (STAGE_FLOW[index + 1] ?? null) : null;
}

/** Where ◀ takes a card: the stage before; a lost lead goes back to Lead (the demo's `prevStage`). */
function previousStage(stage: LEAD_STAGE): LEAD_STAGE | null {
  if (stage === LEAD_STAGE.PERDIDO) return STAGE_AFTER_LOST;

  const index = STAGE_FLOW.indexOf(stage);

  return index > 0 ? (STAGE_FLOW[index - 1] ?? null) : null;
}

/** ✕ is offered while the lead is still in play: not on a client, not on a lead already lost. */
function canMarkLost(stage: LEAD_STAGE): boolean {
  return stage !== LEAD_STAGE.PERDIDO && stage !== LEAD_STAGE.CLIENTE;
}

export { canMarkLost, nextStage, previousStage };
