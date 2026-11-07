---
version: 2.1.0
origin: pendulum
based-on: 2.1.0
---
# RULES — Idea Module

> For AI agents. Idea-specific decisions not covered by `arcus-api-architecture` or the laravel
> skill. The client contract lives in `app.rnc/src/modules/Idea/RULES.md`.

## Domain

**Ideias** (en: **Ideas**): the product's own feedback board. A member writes an idea, everyone
reads it, anyone can support it, and the maintainer answers it with a status and a public note.
`news` is the changelog those answers eventually appear in.

**In identifiers the word is `Idea`; in prose it is "ideia"/"idea".** Same word, two languages —
the identifier/interface split the bootstrap uses elsewhere, not two names for two things.

## This is the only PUBLIC surface in the API (CRITICAL)

Every other module answers *what is mine*: a trail, a week, a season, a routine are scoped to
one user and scoping is the protection. **Ideas inverts that.** One person writes text that
every other person reads, so:

- **Scoping is not the safety here — moderation is.** `hidden_at`, the rate limiter, and the
  attachment rules are load-bearing, not polish.
- **A hidden idea is hidden from EVERYONE, its own author included.** `show` returns 404, and so
  do `PATCH` and `DELETE` (`IdeaService::findWritable`) — a 403 there would confirm the row exists.
  A 403 is kept for an idea the person CAN see but may no longer rewrite. If the
  author could still read it, hiding would be a private conversation between two people rather
  than a moderation decision, and the 404 is what a deleted row would have said anyway.
- **`ideas.read` gates the whole route group.** Someone who cannot see the board has no business
  voting in it or commenting on it either.
- **Comments have their own, looser ceiling** (`throttle:idea-comments`, 40/hour) rather than
  sharing the idea limit (`throttle:ideas`, 5/hour). The person most likely to write many comments
  in an hour is the maintainer answering the board, and five would have locked them out on the
  sixth reply. A new idea is a rare act; a reply is a conversation.

## The three keys, and why they are three

`ideas.read` / `ideas.create` / `ideas.manage` — not the usual read/manage pair.

- **`create` is split from `read`** so a noisy account can be muted without being blinded. Losing
  the ability to write must not cost the ability to see what the product is doing.
- **`manage` is the maintainer's**: status, the public note, hiding, merging.
- **News declares its own `news.manage`.** It used to reuse `ideas.manage`, on the argument that
  it was the same person and the same surface. It stopped being true once the changelog became
  something written to be read — a cover, a standfirst, an article. Writing the product's
  announcements and moderating other people's ideas are different jobs, and one can be granted
  without the other.
- **The keys live in `PermissionGroupsHelper`**, the core file, because the module is part of the
  bootstrap. `RoleSeeder` and `ManagerSeeder` both read that helper, so a key added there is
  granted to the right roles without a second edit — and `ManagerSeeder`'s exclusion list is what
  keeps `ideas.manage` and `news.manage` away from the ordinary member role.

## Intentional decisions

- **`user_id` is `nullOnDelete` on `ideas`, `idea_comments` and `news` — never cascade.** An idea
  other people supported is no longer only its author's; deleting the account would take the
  votes, the thread and the reason a delivered feature exists. A null author renders as a removed
  contributor. `idea_votes` DOES cascade: a vote carries no content of its own.
- **`votes_count` is a denormalised read model; `idea_votes` is the truth.** The board's default
  order is "most supported", and the alternative is a correlated subquery per render for a number
  that changes once per click. Written in the same transaction as the vote, recomputed from the
  rows after every mutation.
- **The unique on `(idea_id, user_id)` is the "one vote per person" rule.** Enforced in a service
  it is a promise that survives until two requests arrive at once; enforced in the index it is
  arithmetic. `IdeaVoteService` checks first, then narrowly catches
  `UniqueConstraintViolationException` for the race — **never a bare `QueryException`**, which
  would report a real fault to the client as a successful vote.
- **The author may support their own idea.** Excluding them makes the ordering punish whoever
  wrote the thing.
- **`Idea::isEditableBy()` is the ONE definition of "may rewrite this".** `IdeaService::canWrite`
  enforces it and `IdeaResource.canEdit` reports it, so the button and the endpoint cannot
  disagree. The rule: the author owns the text only while the idea is `received` AND has no
  supporters; a moderator always may.
- **`delivered_at` is written only by `IdeaModerationService::updateStatus`, and CLEARED when the
  status leaves `delivered`.** Novidades orders by it, so a stale stamp keeps announcing something
  that got walked back. A re-edit of an already-delivered idea keeps the original date.
- **Merging transfers supporters; deleting would discard them.** Anyone who supported both counts
  once. The duplicate is hidden, not deleted, and carries `merged_into_id` so a stale link lands
  somewhere real. The target must be a different idea still on the board — never hidden, never
  merged itself — and a duplicate is merged once; each refusal is a 422 with its own key
  (`ideas.merge.sameIdea` / `targetUnavailable` / `alreadyMerged`), a missing duplicate is 404.
- **A write that touches more than one row is one transaction** — an idea and its image, an entry
  with its areas and cover. `ImageAttachmentService` deletes a new blob on rollback and an old one
  only after commit, so a failed write leaves neither an orphan file nor a row pointing at nothing.
- **`is_official` on a comment is stamped at write time from the author's permission**, never read
  from their role at render time. Someone who answered as the maintainer keeps having answered as
  the maintainer.
- **Deleting a comment is two different gestures, and the SECOND click removes it.** A moderator
  acting on someone else's visible comment hides it; acting on the same comment again removes it
  for good — otherwise hiding is the only thing that can ever happen to spam and the board keeps
  it forever. Anyone deleting their OWN comment deletes it, moderator included: taking back what
  you said is not moderation and should leave no moderated trace.
- **`IdeaCommentResource` carries BOTH `hidden` and `deleted`, and neither implies the other.** A
  comment removed for good was already hidden, so it comes back with `hidden: true`; only
  `deleted` (from `$model->exists`, false after Eloquent deletes) says it is gone. Without it the
  client folds a removed comment back into the thread.
- **`findIdeaById` loads comments under the SAME visibility rule as the dedicated endpoint.** The
  detail screen reads its thread from `GET ideas/{id}`, so filtering hidden comments
  unconditionally left a moderator unable to see the comment they had just hidden — and therefore
  unable to act on it again.
- **`comments_count` counts what a reader can SEE**, so hiding one decrements it.
- **The board costs SIX queries at any size — measured, not assumed.** 200 ideas from six authors:
  two for the viewer's permissions, one for the ideas (with `voted` as a correlated `exists`), one
  for the authors, one for the attachments, one for the cache. `IdeaResource` asks
  `IdeaAccessService::canModerate` per ROW and that still costs two queries total, because
  `PermissionService` memoises on the request — which is the whole reason the resource may call it
  at all. ~100 ms for the full 200.
- **The board returns every match, capped at 200, and does not paginate.** One product's feedback
  board is small and the filters narrow it; infinite scroll on a list people read top to bottom
  buys nothing. The cap is there so the assumption failing costs a truncated list instead of a
  timeout.

## Attachments (CRITICAL)

- **Disk comes from `config('ideas.attachment_disk')` (`IDEA_ATTACHMENT_DISK`, default `public`)
  and is STORED PER ROW.** The disk is configurable so it can change; on the day it moves to
  object storage, rows written before must still resolve to where their file is.
- **SVG is forbidden and the `image` rule alone does not forbid it.** SVG is XML that runs script;
  served from the app's own origin, one upload is stored XSS against everyone who opens the idea.
  The explicit `mimes:jpg,jpeg,png,webp` is the rule that matters.
- **The stored extension comes from a MIME whitelist (`EXTENSION_BY_MIME`), never from
  `$file->extension()` and never from the client's filename.** Validation should make it
  unreachable; it exists because the default disk is served by nginx, and a `.php` under a served
  path is remote code execution rather than a broken image.
- **The filename is a UUID.** A client-supplied name is a traversal and a collision, and carries
  nothing the interface shows.
- **One attachment per idea is what the write path does, not a guard and not a unique index.**
  `Idea::attachment()` is a `HasOne`, and `ImageAttachmentService::replace` deletes the existing row
  (its file goes once the transaction commits) before it writes the new one. Raising the limit is a
  change to that write path and the relation, never a migration. (This line used to say a guard
  clause in `IdeaAttachmentService` enforced it; there is none, and its docblock says why.)
- **The file dies with the row.** An orphaned blob on a 24 GB droplet shared with six projects is
  nobody's friend.
- **`APP_URL` MUST be correct, or every attachment link 404s silently (CRITICAL).** The url comes
  from the disk's configured `url`, which Laravel derives from `APP_URL`. Verified for real against
  the local Valet host: the file was stored correctly and served fine at
  `https://api.rnc.test/storage/…`, but the API answered
  `http://localhost/storage/…` — Laravel's untouched default. `php artisan storage:link` is the
  other half of that deploy step.

  **It happened anyway**, on 2026-09-02, in the very environment this paragraph was written for:
  the rule was here and the local `.env` still said `http://localhost`. Know the symptom, because
  it does not look like a URL problem — the browser falls back to the `<img alt>`, which is the
  entry's TITLE, so the card renders its own headline as bare text sprawling outside the card and
  reads as a broken layout. If somebody reports a screen that "looks wrong" and there should be a
  picture on it, check `APP_URL` before you touch any CSS.

### Testing uploads — two traps

**`UploadedFile::fake()` reports its MIME type from the file NAME**, so a fake `shell.png` full of
PHP passes every mime rule and the test asserts nothing about this code. To test content sniffing,
build a real temp file and wrap it: `new UploadedFile($path, 'shell.png', null, null, true)` — a
null type makes Symfony sniff the bytes, which is what a real upload does.

**`Storage::fake()` never reads the disk's configured `url`** — it merges only the config passed to
`fake()` itself with a temp root. So a faked disk answers with a RELATIVE path, and any test
asserting `toContain('ideas/')` passes whether the url is absolute or not. To assert the real
contract, hand it the url: `Storage::fake('public', ['url' => 'https://…/storage'])`.

## Novidades is an article, and its body is sanitised ON THE WAY IN (CRITICAL)

The body arrives as HTML from a rich-text editor and is STORED as HTML. `NewsBodySanitizer` runs on
create and on update, so what sits in the database is what has already been made safe.

**Never move this to render time.** Cleaning on the way out protects only the screen that remembers
to do it; the row would still hold whatever was pasted, and every other consumer of
`GET /api/news` — a future app, an export, an email — would receive it raw.

"Only admins write it" is not the argument, and must not become one. An admin pastes from
somewhere, an account is borrowed, the key is granted more widely later.

The allow-list is deliberately NARROWER than the editor's toolbar can produce, and
`allowSafeElements()` is not used — it is generous, and generosity is the wrong instinct here.
**Images are refused in the body**: an entry has a cover, uploaded through this API and served from
a disk we control; an `<img>` is a remote URL nobody vetted, loading from a third party in every
reader's browser and telling that third party who read what.

`link` is validated `url:http,https`. The scheme is the whole risk in a link field.

## `news.manage`, split from `ideas.manage`

Writing the changelog and moderating the board are different jobs, and became different keys when
Novidades grew a cover, a standfirst and an article. One can be granted without the other, and
`NewsArticleTest` asserts both refusals — a news writer cannot hide an idea, and an idea moderator
cannot publish an entry.

Reading Novidades still rides on `ideas.read`: reading the changelog is part of using the product.

## One copy of the image rules

`ImageAttachmentService` holds every rule about where an uploaded image goes and what it may be
called — the MIME-derived extension whitelist, the generated filename, the per-row disk.
`IdeaAttachmentService` and `NewsCoverService` are thin wrappers over it that only carry their own
directory and their own "how many" limit.

Two copies of a rule about path traversal is one copy plus a future divergence. If a third feature
stores an image, wrap this service — do not copy it.

## The wire contract is pinned, because nothing else checks it

`Feature/Idea/IdeaContractTest` asserts the EXACT key set of every resource, and the client's
`src/modules/Idea/interfaces.ts` is written against those keys.

Nothing else would catch a drift: **every frontend e2e route-intercepts the API**, so a field
renamed here passes the whole Vitest and Playwright suite and breaks only in a browser. The
assertions are on the exact set rather than on presence — a key that quietly appears is as much a
contract change as one that disappears, and means either dead weight on the wire or a client nobody
updated.

Ids travel as **integers**, foreign ids included (`(int) $this->idea_id`), like every other
resource in the API. (They used to be strings "so the client never does arithmetic on one" — but
`User` and `Tip` sent numbers, the client carried both shapes, and the owner chose one format:
lifeshifter-269.) Counts are integers and flags are booleans; a missing author is `null`, never an omitted key.

## Migrations

`arcus-api-architecture` §3 is absolute here: **no ALTER migrations at all.** The bootstrap
keeps no data, so a table's `create_*` is its only description and it is edited until it is
right, followed by `make fresh`.

The project this module came from had shipped, and reasoned from that: it shipped
`comments_count`, `delivered_at` and `merged_into_id` unused because an ALTER would have cost
it a migration it was not allowed to write. **The columns stay, the reasoning does not.** Here
they are simply columns a planned phase will use.

`idea_attachments` being a TABLE rather than three columns survives on its own merit and has
nothing to do with migrations: raising "one image" to three should be a rule in a service, not
a change of shape.

## Demo data

The bootstrap ships **no** board demo seeder. A project that wants one (lifeshifter's
`IdeaDemoSeeder`) writes it under the rule in `database/RULES.md` ("Demo data runs on demand"):
run by `php artisan db:seed --class=…`, never from `DatabaseSeeder` or `seeders.local.php` — it
creates demo contributors, and a default seed that invents accounts on a live install is how a
personal tool grows people nobody added — and idempotent, every row keyed on its title, so a
screenshot pass can run it repeatedly. (This section used to name `IdeaDemoSeeder` as if it were
here; the command failed with "Target class does not exist".)
