---
version: 2.0.1
origin: pendulum
based-on: 2.0.1
---

# RULES — MNewsForm

> For AI agents. Last updated: 2026-09-17.

The create/edit form for a Novidades entry. Lives in a footerless `MMainDialog`; owns its fields
and validation, and hands the parent a payload.

|       |                                                             |
| ----- | ----------------------------------------------------------- |
| Props | `entry` (null = create), `deliveredIdeaOptions`, `isSaving` |
| Emits | `onClose`, `onSubmit(payload)`                              |

**The parent calls the API, not the form.** This is the one place `MNewsForm` departs from
`07-view-patterns`' molecule contract, and on purpose: create and update are different endpoints
with different multipart shapes, and `NewsView` already owns the granular list update that follows.

## The toolbar mirrors the sanitiser

`NewsBodySanitizer` on the API decides what survives. The toolbar here offers headings, bold /
italic / underline / strike, both lists, blockquote, code block, link and clear — and nothing else,
because everything else is stripped on the way in.

**Change one and you must change the other.** A toolbar button whose output the sanitiser drops is
a feature that appears to work and silently does not. Images are the deliberate omission: the body
takes no `<img>`, the cover is the picture.

**Declare the toolbar through the `#toolbar` slot, never `:modules`.** With `:modules`, PrimeVue
still renders its own default toolbar template and Quill builds a second one from the config — the
screen ends up with two, one of them dead.

## Publishing

The switch here sets the state at save time. One-click publishing from the card is
`PublishNewsService`, a separate endpoint, because a full update reads an absent field as "clear
it" and would wipe a subtitle or link the caller did not resend.
