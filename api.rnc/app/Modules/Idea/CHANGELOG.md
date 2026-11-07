# Changelog — Idea Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.0 — 2026-09-17

### Changed

- `PATCH`/`DELETE /ideas/{id}` answer **404** for an idea the caller cannot see — missing, or hidden from a non-moderator, its own author included — the same answer `show` gives. They used to answer 403 (confirming the hidden row existed) and let the author of a hidden idea rewrite and delete it. A visible idea the caller may no longer edit is still 403. `IdeaWriteVisibilityTest`.
- `POST /ideas/{id}/merge` answers **404** for a missing duplicate and **422** naming the rule for every refusal: `ideas.merge.sameIdea`, `ideas.merge.targetUnavailable` (target hidden or already merged — its supporters would vanish from the board) and `ideas.merge.alreadyMerged` (under `errors.id`). It used to answer 400 "cannot be merged into itself" for all of them, and accepted a merged target. `IdeaMergeTargetTest`.
- `NewsShareController` reads through `NewsService::findShareable` instead of the repository; the "a draft is not shareable" rule lives in the service. `IdeaControllerLayeringTest` keeps controllers off repositories.

### Fixed

- An idea and its image, and a Novidades entry with its areas and cover, are written in one transaction (create, update, delete). A failure in the second write no longer leaves a half-written row behind a 500. `ImageAttachmentService` removes a blob stored before a rollback and deletes a replaced blob only after the commit. `IdeaNewsAtomicWritesTest`.
- `news.link` is `varchar(2048)` and `news.body` is `mediumText` in the create migration: the request accepts 2048 and 60,000 characters, and MySQL strict mode refused both with a 500. `NewsColumnCapacityTest` compiles the migration with the MySQL grammar, since SQLite ignores lengths.
- The share page (`/n/{id}`) sends the reader to `/news?entrada={id}`, the key the SPA's NewsView reads; it sent `?entry=`, so the link from a chat landed on the list and the article never opened. `NewsShareTest` pins the key (from lifeshifter-140/197).
- `StoreIdeaRequest` and `UpdateIdeaRequest` share their rules through `IdeaRequestRules`, as the News requests do through `NewsRequestRules`: the 2 MB ceiling and the format list existed twice (review ARC-21).
- Static analysis: `@mixin` on the four resources, `@property` for the casts and the computed aggregates, relation generics, and a template on `ImageAttachmentService::replace` (it now takes the relation, `$idea->attachment()`). Full `phpstan analyse` reports 0 errors.

### Documented

- Attachments: "one per idea" is what `ImageAttachmentService::replace` does on a `HasOne`, not a guard clause in `IdeaAttachmentService` — the rules contradicted the service's docblock.
- Demo data: the bootstrap ships no `IdeaDemoSeeder`; the section now points at the on-demand rule in `database/RULES.md` instead of a command that failed with "Target class does not exist" (from lifeshifter-148).

### Notes

- ARC-12 was not a defect: `firstOrCreate` inserts through `createOrFirst`, which catches the unique violation in a savepoint and reads the winner. `NewsEngagementRaceTest` now holds that promise, and the repository docblocks say why.

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** every resource sends ids — and the foreign ids `ideaId` and `mergedIntoId` — as integers. They were strings here while `User` and `Tip` sent numbers, and the client carried both shapes; the owner chose numbers (from lifeshifter-269). `IdeaContractTest` asserts the integer type, including `mergedIntoId`, and the feature tests compare with the integer id.

## 1.5.0 — 2026-09-10

### Fixed

- Every endpoint that answers with a news entry now dresses it with the engagement aggregates.
  `create`, `update` and `setPublished` returned a bare model, and `NewsResource` publishes those
  four fields under `when(isset(...))` — so they were not sent wrong, they were not sent at all.
  The client patched its list from that answer and read `read` as `undefined`, which put a freshly
  written entry under neither "unread" nor "read" until the page was reloaded.

## 1.4.0 — 2026-09-10

### Added

- `clearAreas` on the update request: the empty answer a multipart caller cannot otherwise write.
  Multipart has no way to spell an empty array, so a writer removing the last tag sent a request
  indistinguishable from one that never mentioned areas — and `syncAreas` left them standing.

## 1.3.0 — 2026-09-01

### Added

- **Novidades became an article**: `subtitle`, `link` and a cover image, so an entry can be written
  to be read rather than skimmed. The source project added these by ALTER because it had shipped;
  here they are folded into `create_news_table`, which is the only form this repository allows.
- `news_covers`, mirroring `idea_attachments`. A table and not four columns, so raising "one cover"
  to a gallery stays an edit to a guard clause.
- **`NewsBodySanitizer`** — the body is editor HTML, stored as HTML, sanitised on the way IN. The
  allow-list is narrower than the toolbar; images are refused in the body because the cover exists.
- **`news.manage`**, split from `ideas.manage`. Writing the changelog and moderating the board are
  different jobs.
- 18 Pest tests across the sanitiser, the cover and the permission split.

### Changed

- `ImageAttachmentService` now holds the image rules in ONE copy; `IdeaAttachmentService` and
  `NewsCoverService` are thin wrappers. Two copies of a path-traversal rule is one copy plus a
  future divergence.

## 1.2.0 — 2026-09-01

### Added

- `IdeaContractTest` — eight tests pinning the exact key set of every resource, plus that ids are
  strings, counts integers, flags booleans, and a missing author `null` rather than absent. Nothing
  else checked this: every frontend e2e route-intercepts the API, so a renamed field would pass the
  whole client suite and break only in a browser. Written after diffing the running API's actual
  responses against `interfaces.ts`, which matched exactly.

## 1.1.1 — 2026-09-01

### Added

- A test pinning that an attachment's url comes from the disk's configured `url`. Found by putting
  a real image through the whole stack with curl: the file stored and served correctly, but the API
  answered `http://localhost/storage/…` because the local `APP_URL` was still Laravel's untouched
  default — a silent 404 whose only symptom is a broken image. `APP_URL` and `storage:link` are now
  written down in RULES.md as the two halves of that deploy step.
- Documented that `Storage::fake()` discards the disk's configured `url`, so no faked-disk test can
  verify an absolute url unless it passes one in.

## 1.1.0 — 2026-09-01

### Fixed

- **Comments shared the 5/hour idea ceiling**, which would have locked the maintainer out on their
  sixth reply of an hour — exactly the person who writes many comments. They now have their own
  `throttle:idea-comments` limiter at 40/hour.

### Removed

- `GET ideas/delivered`. Nothing consumed it and nothing tested it: Novidades ended up with its own
  table, which is the honest shape, so the fallback it was built as never happened.

### Added

- Three Pest tests pinning both ceilings and that they count per account, not per address.

## 1.0.1 — 2026-09-01

### Fixed

- **A moderator could not see the comment they had just hidden.** `GET ideas/{id}` filtered hidden
  comments unconditionally while `GET ideas/{id}/comments` showed them to a moderator; the detail
  screen reads the first, so hiding a comment made it vanish for the moderator too and left them
  unable to act on it again. Both paths now share one visibility rule.
- **Hiding a comment was a one-way door.** A moderator acting on an already-hidden comment re-hid
  it forever, so spam could never be removed. A second act now removes it for good.
- **A removed comment was indistinguishable from a hidden one on the wire.** It was already hidden,
  so it came back with `hidden: true` and the client folded it back into the thread.
  `IdeaCommentResource` now also carries `deleted`.

## 1.0.0 — 2026-09-01

### Added

- The Ideias board: the product's own feedback surface, and the first PUBLIC surface in this API.
  Five tables (`ideas`, `idea_votes`, `idea_comments`, `idea_attachments`, `news`), fourteen
  routes, three permission keys.
- **Board and authoring** — create, list with status/type/area/text filters and two orderings,
  read one, rewrite and withdraw. The author owns the text only while nobody has acted on it;
  `Idea::isEditableBy()` is the single definition the endpoint enforces and the resource reports.
- **Support** — one vote per person, guaranteed by a unique index rather than by a service
  promise. Idempotent under a double click and under a genuine race.
- **Moderation** — status with a public note, reversible hiding, and merging a duplicate into the
  idea it duplicates with its supporters transferred.
- **Comments** — flat, with the maintainer's reply stamped official at write time. A moderator
  hides someone else's words; anyone deletes their own.
- **Attachments** — one image per idea, disk configurable via `IDEA_ATTACHMENT_DISK`, SVG refused,
  the stored extension taken from a MIME whitelist and the filename generated.
- **Novidades** — the changelog, as its own table rather than a view over delivered ideas, because
  most of what ships was never proposed on the board.
- `IdeaDemoSeeder`, deterministic and idempotent, on demand only.
- 81 Pest feature tests across board, votes, edit rules, moderation, attachments, comments, news
  and the permission gate.

### Notes

- `Feature/Timebox/SeedShapeTest` pins the contributor role's exact permission set, so it was updated
  in the same change to carry `ideas.read` and `ideas.create` — and to keep failing loudly if
  `ideas.manage` is ever granted to a contributor by accident.
