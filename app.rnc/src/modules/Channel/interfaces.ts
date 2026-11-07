/** A stage of the channel (`ChannelStage`, rnc-3 R6). */
type ChannelStage = 'ativo' | 'contato' | 'demo' | 'implantacao';

/**
 * `ChannelFirmResource`: one RNC firm on the board. The fee, the share rate and the notes come only
 * to `channel.manage` (AM15) — Reinaldo's rows carry none of the three keys, so they are optional.
 */
interface ChannelFirm {
  city: string;
  id: number;
  monthly_fee_cents?: number;
  name: string;
  next_stage: ChannelStage | null;
  notes?: string | null;
  share_bp?: number;
  share_cents: number;
  stage: ChannelStage;
  stage_at: string;
}

/** The board's summary (`ChannelService::summary`): Franco's share Σ, a month and a year, Σ to come, counts. */
interface ChannelSummary {
  counts: Record<ChannelStage, number>;
  monthly_share_cents: number;
  pending_monthly_share_cents: number;
  yearly_share_cents: number;
}

/** What a new firm starts on (`config/channel.php`) — the operator's board only (AM15). */
interface ChannelDefaults {
  monthly_fee_cents: number;
  share_bp: number;
}

/** `GET /api/channel` and every write's answer (`ChannelResource`, W41): the whole board. */
interface ChannelBoard {
  defaults?: ChannelDefaults;
  firms: ChannelFirm[];
  summary: ChannelSummary;
}

/** `POST /api/channel/firms` and `PATCH /api/channel/firms/{id}` — what the requests validate. */
interface ChannelFirmPayload {
  city: string;
  monthly_fee_cents: number;
  name: string;
  notes: string | null;
  share_bp: number;
}

export type { ChannelBoard, ChannelDefaults, ChannelFirm, ChannelFirmPayload, ChannelStage, ChannelSummary };
