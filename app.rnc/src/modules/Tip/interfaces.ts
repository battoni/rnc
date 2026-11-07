interface TipAuthor {
  id: number;
  name: string;
}

/** A tip as the management view sees it. */
interface Tip {
  anchor_key: string;
  archived_at: string | null;
  content: string;
  created_at: string;
  created_by: TipAuthor | null;
  id: number;
  updated_at: string;
  updated_by: TipAuthor | null;
}

/** What `GET /tips/active` returns: only what rendering a popover needs. */
interface TipContent {
  anchor_key: string;
  content: string;
  id: number;
}

interface TipHistoryEntry {
  change_reason: string | null;
  changed_by: TipAuthor | null;
  content: string;
  created_at: string;
  event: string;
  id: number;
  /** Id of the version this one was restored from; the label is rendered from it. */
  restored_from_id: number | null;
}

interface TipPayload {
  anchor_key?: string;
  change_reason?: string | null;
  content?: string;
}

export type { Tip, TipAuthor, TipContent, TipHistoryEntry, TipPayload };
