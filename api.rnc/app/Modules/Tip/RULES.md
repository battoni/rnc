---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — Tip Module (api)

Editable contextual help. Layered DDD (Controller → DTOs → Service → Repository → Resources),
mirroring the Reference module.

## What a tip is

A tip is a body of HTML bound to an **anchor** — a place in the UI where `<ATip>` renders. The
catalogue of anchors lives in the FRONTEND (`app.rnc/src/constants/tipAnchor.constant.ts`),
next to the call sites it describes. This module treats `anchor_key` as an opaque string and
validates its shape, not its membership: a key matching no anchor simply never renders.

## Sanitising (CRITICAL)

- **`TipContentSanitizer` runs on the way IN, never on the way out.** What is stored is what has
  already been made safe, so every consumer — this API, a future export, an email — gets safe
  HTML without having to remember to clean it.
- **The allow-list is narrower than the editor's toolbar, never wider.** The frontend toolbar in
  `MAddEditTipForm` mirrors it. A button that inserts something the sanitiser then discards is
  worse than no button: the author sees it work, saves, and finds it gone.
- **No `<img>`, no `allowSafeElements()`.** Links keep `href` only, http(s) only.
- **Non-breaking spaces are collapsed to ordinary spaces.** Quill serialises EVERY space as an
  nbsp, and such a paragraph refuses to wrap — it overflows the popover and drags a horizontal
  scrollbar across the page. Normalised on write rather than patched with CSS at each render site,
  because the defect is in what is stored and would survive into an export or an email.
- Do NOT relax this because "only `tips.manage` writes tips". Every authenticated user READS
  them, on the screens they are anchored to.

## History (CRITICAL)

- `tip_histories` is an **append-only event log**, not the Reference Time-Ledger. There is no
  `starts_at`/`ends_at`: a tip is never read retroactively, so temporal validity would imply a
  query nobody makes.
- Every write appends a row **in the same transaction as the write**. Never make the log a
  separate step that a later refactor can drop.
- Rows carry the **full content of that version**, not a diff — a restore has to reproduce the
  text exactly, and a diff chain only does that while every link survives.
- `event` distinguishes `created` / `updated` / `restored` / `archived` / `unarchived`. Archiving
  changes no text, so a content-only log would let a tip vanish with nobody's name against it.
- **Restore appends; it never rewinds.** The new row carries `restored_from_id`, and the version
  restored from stays untouched and visible.
- `restored_from_id` is structured on purpose: the frontend renders "restored the version from
  12/03" in the reader's language, instead of freezing a sentence in the restorer's locale.

## Change reason

- **Required for a content change** (raised from `TipService`, not the FormRequest — only the
  service knows whether the sanitised content actually differs) and for **archiving**.
- **Not required** to move a tip to another anchor: that rewrites nothing anyone read, and
  demanding a sentence for it trains people to type "." to get past the field, which is how a
  reason column stops meaning anything.

## Anchor occupancy

One active tip per anchor. Enforced in `TipService::assertAnchorIsFree`, **not** by a unique
index: archiving frees the anchor, and MySQL has no partial unique index for "unique among the
non-archived". Unarchiving re-checks, because the anchor may have been given away meanwhile.

## Refusals are keys

The three refusals `TipService` raises answer an i18n key under the field — `tips.errors.anchorTaken`
(`anchor_key`), `tips.errors.reasonRequired` (`change_reason`), `tips.errors.contentRequired`
(`content`) — and the SPA's locales hold the sentences. The API has no `lang/*/tips.php`.
**Retired:** the sentences used to live there and leave through `__()`, already translated by the
request's locale; the SPA looked the sentence up as a key, found nothing, and showed the generic
error toast. `TipRefusalKeysTest` pins the three keys.

## Permissions

| Route | Gate | Why |
| --- | --- | --- |
| `GET /api/tips/active` | `auth` only | A tip is ambient help; gating it would blank the icon for exactly the people it exists for. |
| `GET /api/tips`, `GET /api/tips/{id}`, `.../history` | `tips.read` | Management view and audit trail. |
| `POST`, `PATCH`, `DELETE`, `restore`, `unarchive` | `tips.manage` | Mutations. |

`GET /api/tips/active` returns `TipContentResource` — id, anchor, content, nothing else.
Authorship is management information and must not ride along in a payload every user fetches
every session.

## Do Not

- Do not add an `is_active` flag alongside `archived_at` — one state column.
- Do not hard-delete a tip. Archiving is the removal; the log outlives the text.
- Do not let `TipSeeder` overwrite an existing tip. The database wins over what a developer once
  wrote — that is the whole feature.
