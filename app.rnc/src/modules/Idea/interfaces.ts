import type { IDEA_AREA, IDEA_SORT, IDEA_STATUS, IDEA_TYPE } from './enums';
import type { IdeaAreaValue } from './types';

/** Null when the account behind an idea is gone — the row outlives the login. */
interface IdeaAuthor {
  id: number;
  name: string;
}

interface IdeaAttachment {
  url: string;
  mime: string;
  size: number;
}

/**
 * `deleted` and `hidden` are BOTH needed and neither implies the other. A moderator's first act on
 * someone else's comment hides it; a second removes it for good — and a comment removed for good
 * was already hidden, so it comes back with `hidden: true`. Only `deleted` says it is gone.
 */
interface IdeaComment {
  id: number;
  ideaId: number;
  body: string;
  isOfficial: boolean;
  hidden: boolean;
  deleted: boolean;
  createdAt: string | null;
  author?: IdeaAuthor | null;
}

/**
 * `voted` and `canEdit` are viewer-relative and arrive WITH the row. `canEdit` in particular is
 * never recomputed here: the API owns the rule, and a second copy of it in the client is how the
 * button and the endpoint start disagreeing.
 */
interface Idea {
  id: number;
  title: string;
  description: string;
  type: IDEA_TYPE;
  area: IDEA_AREA | null;
  status: IDEA_STATUS;
  adminNote: string | null;
  votesCount: number;
  commentsCount: number;
  voted: boolean;
  canEdit: boolean;
  hidden: boolean;
  mergedIntoId: number | null;
  deliveredAt: string | null;
  createdAt: string | null;
  author?: IdeaAuthor | null;
  attachment?: IdeaAttachment | null;
  comments?: IdeaComment[];
}

/**
 * `body` is HTML and safe to render — the API sanitises it on write, so the client is not the thing
 * standing between an editor and stored XSS. See the API module's RULES.
 */
interface NewsEntry {
  id: number;
  title: string;
  subtitle: string | null;
  body: string;
  link: string | null;
  /** Which parts of the product the entry is about — the Ideas board's vocabulary. */
  areas: IdeaAreaValue[];
  published: boolean;
  publishedAt: string | null;
  createdAt: string | null;
  cover?: IdeaAttachment | null;
  idea?: {
    id: number;
    title: string;
    author: IdeaAuthor | null;
  } | null;
  /** The room's applause, and this reader's own share of it. */
  reactions?: NewsReactionCounts;
  myReactions?: NewsReactionCounts;
  read?: boolean;
  /** Absent for a reader — the API does not tell the audience how large the audience is. */
  readCount?: number;
}

interface IdeaFilters {
  status: IDEA_STATUS | null;
  type: IDEA_TYPE | null;
  area: IDEA_AREA | null;
  search: string;
  mine: boolean;
  sort: IDEA_SORT;
}

/**
 * The form's own shape. `attachment` is a File because the request is multipart, and
 * `removeAttachment` is separate from it because "sent no file" and "delete the file" are
 * different intents a multipart body cannot otherwise tell apart.
 */
interface IdeaPayload {
  title: string;
  description: string;
  type: IDEA_TYPE;
  area: IDEA_AREA | null;
  attachment?: File | null;
  removeAttachment?: boolean;
}

interface IdeaStatusPayload {
  status: IDEA_STATUS;
  adminNote: string | null;
}

interface NewsPayload {
  title: string;
  subtitle: string | null;
  body: string;
  link: string | null;
  areas: IdeaAreaValue[];
  ideaId: number | null;
  publish: boolean;
  cover?: File | null;
  removeCover?: boolean;
}

/** What a status looks like: its label key, its colour tokens and its icon. */
interface IdeaStatusMeta {
  labelKey: string;
  icon: string;
  classList: string;
}

/** Somebody who read an entry, and when they first did. */
interface NewsReader {
  id: number;
  readAt: string | null;
  user: { id: number; name: string } | null;
}

/** Counts per reaction kind, keyed by `NEWS_REACTION`. */
type NewsReactionCounts = Record<string, number>;

export type {
  Idea,
  IdeaAttachment,
  IdeaAuthor,
  IdeaComment,
  IdeaFilters,
  IdeaPayload,
  IdeaStatusMeta,
  IdeaStatusPayload,
  NewsEntry,
  NewsPayload,
  NewsReactionCounts,
  NewsReader,
};
