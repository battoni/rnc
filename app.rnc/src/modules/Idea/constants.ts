import type { IdeaFilters, IdeaStatusMeta } from './interfaces';
import { IDEA_AREA, IDEA_SORT, IDEA_STATUS, IDEA_TYPE } from './enums';

/**
 * How each status looks. Two decisions are load-bearing here.
 *
 * `NOT_NOW` is `contrast`, never `danger`. The brief asks for a product that does not judge, and a
 * refusal painted in error red reads as a judgement of whoever wrote the idea — it is a scoping
 * decision, and saying it out loud is what keeps people writing.
 *
 * `RECEIVED` is the muted panel rather than a colour: a brand-new idea has not been answered yet,
 * and giving it a hue would make "nobody has looked at this" look like a state somebody chose.
 */
const IDEA_STATUS_META: Record<IDEA_STATUS, IdeaStatusMeta> = {
  [IDEA_STATUS.RECEIVED]: {
    labelKey: 'ideas.status.received',
    icon: 'pi pi-inbox',
    classList: 'bg-panel-muted text-muted',
  },
  [IDEA_STATUS.UNDER_REVIEW]: {
    labelKey: 'ideas.status.underReview',
    icon: 'pi pi-search',
    classList: 'bg-info-100 text-info-700',
  },
  [IDEA_STATUS.PLANNED]: {
    labelKey: 'ideas.status.planned',
    icon: 'pi pi-calendar',
    classList: 'bg-primary-100 text-primary-950',
  },
  [IDEA_STATUS.IN_PROGRESS]: {
    labelKey: 'ideas.status.inProgress',
    icon: 'pi pi-spinner',
    classList: 'bg-warn-100 text-warn-700',
  },
  [IDEA_STATUS.DELIVERED]: {
    labelKey: 'ideas.status.delivered',
    icon: 'pi pi-check-circle',
    classList: 'bg-success-100 text-success-700',
  },
  [IDEA_STATUS.NOT_NOW]: {
    labelKey: 'ideas.status.notNow',
    icon: 'pi pi-moon',
    classList: 'bg-contrast-100 text-contrast-700',
  },
};

const IDEA_TYPE_META: Record<IDEA_TYPE, { labelKey: string; icon: string }> = {
  [IDEA_TYPE.IMPROVEMENT]: { labelKey: 'ideas.type.improvement', icon: 'pi pi-sparkles' },
  [IDEA_TYPE.PROBLEM]: { labelKey: 'ideas.type.problem', icon: 'pi pi-exclamation-circle' },
  [IDEA_TYPE.IDEA]: { labelKey: 'ideas.type.idea', icon: 'pi pi-lightbulb' },
};

const IDEA_STATUS_OPTIONS = Object.values(IDEA_STATUS).map((status) => ({
  value: status,
  labelKey: IDEA_STATUS_META[status].labelKey,
}));

const IDEA_TYPE_OPTIONS = Object.values(IDEA_TYPE).map((type) => ({
  value: type,
  labelKey: IDEA_TYPE_META[type].labelKey,
}));

const IDEA_AREA_OPTIONS = Object.values(IDEA_AREA).map((area) => ({
  value: area,
  labelKey: `ideas.area.${area}`,
}));

const IDEA_SORT_OPTIONS = [
  { value: IDEA_SORT.VOTES, labelKey: 'ideas.sort.votes' },
  { value: IDEA_SORT.RECENT, labelKey: 'ideas.sort.recent' },
];

const DEFAULT_IDEA_FILTERS: IdeaFilters = {
  status: null,
  type: null,
  area: null,
  search: '',
  mine: false,
  sort: IDEA_SORT.VOTES,
};

/**
 * Which area the quick-capture dialog pre-selects, from the route the person was on when they
 * opened it.
 *
 * This is the whole reason the dialog is global rather than a button on one screen: the moment a
 * person has something to say is the moment they hit the thing, and asking them to classify it
 * afterwards is asking a question they have already stopped caring about. Route names are the
 * language-neutral half of the router (`tracks-pt-BR` and `tracks-en` both start `tracks`).
 */
const ROUTE_TO_IDEA_AREA: Record<string, IDEA_AREA> = {
  home: IDEA_AREA.HOME,
  personalization: IDEA_AREA.ACCOUNT,
  users: IDEA_AREA.ACCOUNT,
  'roles-and-permissions': IDEA_AREA.ACCOUNT,
};

const IDEA_TITLE_MAX_LENGTH = 120;

const IDEA_DESCRIPTION_MAX_LENGTH = 5000;

const IDEA_COMMENT_MAX_LENGTH = 2000;

/** Kept in step with StoreIdeaRequest — 2 MB, and four raster formats. SVG runs script. */
const IDEA_ATTACHMENT_MAX_BYTES = 2 * 1024 * 1024;

const IDEA_ATTACHMENT_ACCEPT = 'image/jpeg,image/png,image/webp';

/**
 * Which status the viewer last SAW on each idea they support, so the board can quietly mark the
 * ones that moved. Per-viewer and per-browser on purpose — see `useIdeaStatusWatch`.
 */
const IDEA_STATUS_SEEN_STORAGE_KEY = 'app.rnc:ideas-seen-status';

/** Kept in step with StoreNewsRequest — 2 MB, four raster formats. SVG runs script. */
const NEWS_COVER_MAX_BYTES = 2 * 1024 * 1024;

const NEWS_COVER_ACCEPT = 'image/jpeg,image/png,image/webp';

const TOAST_ERROR_LIFE_MS = 5000;

const TOAST_SUCCESS_LIFE_MS = 3000;

/**
 * The glyph for each reaction. Emoji rather than an icon font because PrimeIcons has no clap and
 * no rocket, and because a flying emoji is the right register for applause — an icon-font glyph
 * tumbling across the card would read as a UI element that came loose.
 */
const NEWS_REACTION_GLYPHS: Record<string, string> = {
  clap: '\u{1F44F}',
  heart: '\u2764\uFE0F',
  rocket: '\u{1F680}',
};

export {
  DEFAULT_IDEA_FILTERS,
  IDEA_AREA_OPTIONS,
  IDEA_ATTACHMENT_ACCEPT,
  IDEA_ATTACHMENT_MAX_BYTES,
  IDEA_COMMENT_MAX_LENGTH,
  IDEA_DESCRIPTION_MAX_LENGTH,
  IDEA_SORT_OPTIONS,
  IDEA_STATUS_META,
  IDEA_STATUS_OPTIONS,
  IDEA_STATUS_SEEN_STORAGE_KEY,
  IDEA_TITLE_MAX_LENGTH,
  IDEA_TYPE_META,
  IDEA_TYPE_OPTIONS,
  NEWS_COVER_ACCEPT,
  NEWS_COVER_MAX_BYTES,
  NEWS_REACTION_GLYPHS,
  ROUTE_TO_IDEA_AREA,
  TOAST_ERROR_LIFE_MS,
  TOAST_SUCCESS_LIFE_MS,
};
