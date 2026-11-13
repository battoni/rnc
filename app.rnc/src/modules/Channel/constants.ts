import type { ChannelStage } from './interfaces';

/** The screens this module owns, linked by name (celer-05): the operator's and Reinaldo's rails link here. */
const CHANNEL_ROUTE_NAMES = { ceo: 'ceo-channel', operator: 'channel' } as const;

/** Both screens read with `channel.read` (ceo, operador); only the operator holds `channel.manage` (R7, R9). */
const CHANNEL_PERMISSIONS = { manage: 'channel.manage', read: 'channel.read' } as const;

/** The channel's stages, in order (R6, `ChannelStage`): one step forward at a time, `ativo` terminal. */
const CHANNEL_STAGES: readonly ChannelStage[] = ['contato', 'demo', 'implantacao', 'ativo'];

/** The stage whose firms pay: only an `ativo` firm's share counts in the hero (R8). */
const CHANNEL_ACTIVE_STAGE: ChannelStage = 'ativo';

/** The field limits of the firm form (`StoreChannelFirmRequest`). */
const CHANNEL_FIELD_LIMITS = { city: 80, name: 120, notes: 500 } as const;

/** The fee's bounds in cents and the share's in basis points (AM9): R$ 0,01–100.000,00 and 0–50 %. */
const CHANNEL_FEE_BOUNDS = { max: 10_000_000, min: 1 } as const;
const CHANNEL_SHARE_BOUNDS = { max: 5000, min: 0 } as const;

/** Basis points in one percent — the form edits the share as a percent, the API keeps it in bp. */
const BASIS_POINTS_PER_PERCENT = 100;

/** The refusal the screen branches on (`ChannelException::CODE_INVALID_TRANSITION`): reload the board. */
const CHANNEL_ERROR_CODES = { invalidTransition: 'invalid_transition' } as const;

/** Franco's share of a fee, as the API rounds it (`ChannelShare::of`, half-up) — for the form's preview only. */
function channelShareOf(feeCents: number, shareBp: number): number {
  return Math.floor((feeCents * shareBp + 5000) / 10000);
}

export {
  BASIS_POINTS_PER_PERCENT,
  CHANNEL_ACTIVE_STAGE,
  CHANNEL_ERROR_CODES,
  CHANNEL_FEE_BOUNDS,
  CHANNEL_FIELD_LIMITS,
  CHANNEL_PERMISSIONS,
  CHANNEL_ROUTE_NAMES,
  CHANNEL_SHARE_BOUNDS,
  CHANNEL_STAGES,
  channelShareOf,
};
