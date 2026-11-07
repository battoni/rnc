/**
 * Where an idea stands. Mirrors `IdeaStatus` on the API; the board's whole credibility rests on
 * this being visible and honest.
 */
enum IDEA_STATUS {
  RECEIVED = 'received',
  UNDER_REVIEW = 'under_review',
  PLANNED = 'planned',
  IN_PROGRESS = 'in_progress',
  DELIVERED = 'delivered',
  NOT_NOW = 'not_now',
}

/** What arrived: something broken, something to sharpen, or something new. */
enum IDEA_TYPE {
  IMPROVEMENT = 'improvement',
  PROBLEM = 'problem',
  IDEA = 'idea',
}

/** Which surface the idea is about — the same list the navigation offers. */
/**
 * Mirrors `IdeaArea` on the API, and must stay in step with it: the server validates against its
 * own enum, so a case offered here and missing there is a select that fails on submit.
 *
 * The bootstrap ships the neutral cases only. A product adds its own screens in BOTH places.
 */
enum IDEA_AREA {
  HOME = 'home',
  ACCOUNT = 'account',
  GENERAL = 'general',
}

enum IDEA_SORT {
  VOTES = 'votes',
  RECENT = 'recent',
}

/**
 * The three ways to applaud an entry.
 *
 * Three and no more, on purpose. A reaction set that grows into a drawer of twenty emoji stops
 * being a gesture and becomes a decision, and a decision is exactly the friction applause must not
 * have. Mirrors `NewsReactionType` on the API — add one there first.
 */
enum NEWS_REACTION {
  CLAP = 'clap',
  HEART = 'heart',
  ROCKET = 'rocket',
}

/**
 * Which side of "I have read this" the list is showing.
 *
 * `ALL` is the default and the only one that shows drafts: a draft has no audience yet, so it is
 * neither read nor unread.
 */
enum NEWS_READ_FILTER {
  ALL = 'all',
  UNREAD = 'unread',
  READ = 'read',
}

export { IDEA_AREA, IDEA_SORT, IDEA_STATUS, IDEA_TYPE, NEWS_READ_FILTER, NEWS_REACTION };
