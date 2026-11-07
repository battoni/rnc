---
version: 1.0.2
origin: pendulum
based-on: 1.0.2
---

# RULES — MImageUpload

> For AI agents. Last updated: 2026-09-17.

One image, chosen by picker, drag, or paste. Used by `MNewsForm` for an entry's cover and built to
be used anywhere else an image is attached.

## The contract

|                  |                                                                                   |
| ---------------- | --------------------------------------------------------------------------------- |
| Props            | `accept` (MIME list, default JPEG/PNG/WebP), `currentUrl`, `disabled`, `maxBytes` |
| Emits            | `onChange(File \| null)`                                                          |
| Owns             | preview, validation, the three input paths                                        |
| Does **not** own | uploading, the field label, the form                                              |

**The unit never uploads.** It hands the parent a `File` and stops. The parent decides which
endpoint it belongs to and when it goes. A unit that both picks and uploads is a unit that can only
ever serve one endpoint.

**`null` means clear, not "unchanged".** The parent distinguishes them: no event at all is
unchanged, `onChange(null)` is a deliberate removal. `MNewsForm` maps the second to `removeCover`.

## The drag state is a border

While a file is dragged over it, the zone takes `border-primary-500` and keeps its own
`bg-panel-muted`. It used to add `bg-primary-50` as well: a light tint that does not flip with the
scheme, under `text-muted` words that do — 3.34:1 in dark. A tint behind flipping text is not a
state colour (`styles/theme/RULES.md`, "What sits ON a fill").

## The paste listener

It is on `window`, not on the drop zone, because a clipboard paste has no hover target — the person
copies a screenshot and hits paste, and there is nothing sensible to have focused first.

Two consequences, both load-bearing:

- **It only acts on an image item**, and only then calls `preventDefault()`. Pasting text into
  another field of the same form must reach that field untouched.
- **Mounting two of these at once means both answer the same paste.** If a screen ever needs two,
  give the unit a scope before it does.

## Object URLs

The preview is an object URL, which pins the file in memory until it is revoked. The unit revokes
the previous one on every change and again on unmount. A change here without a matching revoke is a
leak that no test failure will point at, so `MImageUpload.spec.ts` asserts the revoke directly.

## Do not

- Upload from inside the unit.
- Widen `accept` without widening the server's rule to match — the server is the one that decides,
  and a client that accepts more only produces a worse error later.
- Drop the `data-testid` hooks; the spec and the e2e specs both address the unit through them.
